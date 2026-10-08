<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Auth;

use DateTimeImmutable;
use Fundly\Modules\Access\Domain\LockoutPolicy;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\Totp;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Modules\Licensing\Contracts\LicenceEntitlements;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditOutcome;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\ProblemException;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Pii\PiiMasker;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\PrincipalKind;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Local identity store authentication (LOS-FR-302): Argon2id password,
 * mandatory TOTP MFA (FR-SEC-013), lockout with backoff, step-up, session
 * establishment with the concurrent-session cap (FR-SEC-015). Every outcome
 * is audit-logged (FR-AUD-007).
 */
final class AuthenticationService
{
    public const PENDING_KEY = 'auth.pending';

    private const PENDING_TTL_SECONDS = 300;

    private const MFA_FIELD = 'users.mfa_secret';

    public function __construct(
        private readonly Hasher $hasher,
        private readonly StatefulGuard $guard,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
        private readonly ActorProvider $actors,
        private readonly CurrentPrincipal $principal,
        private readonly FieldEncryptor $encryptor,
        private readonly GrantRepository $grants,
        private readonly LicenceEntitlements $licence,
        private readonly SessionPolicyProvider $sessionPolicy,
        private readonly TenantContext $tenant,
        private readonly ConnectionInterface $db,
    ) {}

    public function attemptPassword(string $email, string $password, Session $session): LoginResult
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
        $now = $this->clock->now();

        if (! $user instanceof User || $user->isService() || ! $user->isActive() || $user->password === null) {
            // Equalise timing with a real verification.
            $this->hasher->check($password, $this->dummyHash());
            $this->auditAnonymous('auth.login.failed', ['email' => $email, 'reason' => 'unknown_or_inactive']);
            throw new AuthenticationFailed;
        }
        if ($user->locked_until !== null && $user->locked_until->toDateTimeImmutable() > $now) {
            $this->auditUser($user, 'auth.login.failed', AuditOutcome::Failure, ['reason' => 'locked', 'locked_until' => $user->locked_until->toIso8601ZuluString()]);
            throw new AuthenticationFailed;
        }
        if (! $this->hasher->check($password, $user->password)) {
            $this->registerFailure($user, 'bad_password');
            throw new AuthenticationFailed;
        }
        if ($this->hasher->needsRehash($user->password)) {
            $user->password = $this->hasher->make($password);
        }

        $this->assertLicenceAllowsLogin($user);
        $session->regenerate();

        if (! (bool) config('fundly.auth.mfa_required', true)) {
            $user->failed_login_count = 0;
            $user->save();

            return $this->complete($user, $session);
        }

        $enrolling = $user->mfa_confirmed_at === null;
        $result = LoginResult::mfaRequired();
        if ($enrolling) {
            $secret = Totp::generateSecret();
            $this->tenant->run($user->tenant_id, function () use ($user, $secret): void {
                $user->mfa_secret = $this->encryptor->encrypt($secret, self::MFA_FIELD);
            });
            $result = LoginResult::enrollmentRequired($secret, Totp::provisioningUri($secret, $user->email, (string) config('fundly.auth.mfa_issuer', 'Fundly LOS')));
        }
        $user->save();

        $session->put(self::PENDING_KEY, [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'enrolling' => $enrolling,
            'expires' => $now->getTimestamp() + self::PENDING_TTL_SECONDS,
        ]);
        $this->auditUser($user, 'auth.login.password_verified', AuditOutcome::Success, ['mfa' => $enrolling ? 'enrollment_required' : 'required']);

        return $result;
    }

    public function verifyMfa(string $code, Session $session): LoginResult
    {
        /** @var array{user_id?: string, tenant_id?: string, enrolling?: bool, expires?: int}|null $pending */
        $pending = $session->get(self::PENDING_KEY);
        $now = $this->clock->now();
        if (! is_array($pending) || ! isset($pending['user_id'], $pending['expires']) || $pending['expires'] < $now->getTimestamp()) {
            $session->forget(self::PENDING_KEY);
            throw new AuthenticationFailed('No sign-in is awaiting MFA, or it has expired. Sign in again.');
        }
        $user = User::query()->find($pending['user_id']);
        if (! $user instanceof User || ! $user->isActive() || $user->mfa_secret === null) {
            throw new AuthenticationFailed;
        }
        if ($user->locked_until !== null && $user->locked_until->toDateTimeImmutable() > $now) {
            throw new AuthenticationFailed;
        }

        $step = $this->checkTotp($user, $code, $now);
        if ($step === null) {
            $this->registerFailure($user, 'bad_mfa_code');
            throw new AuthenticationFailed;
        }

        $user->mfa_last_used_step = $step;
        if (($pending['enrolling'] ?? false) === true) {
            $user->mfa_confirmed_at = Carbon::instance($now);
            $this->auditUser($user, 'auth.mfa.enrolled', AuditOutcome::Success, []);
        }
        $user->failed_login_count = 0;
        $user->locked_until = null;
        $user->save();
        $session->forget(self::PENDING_KEY);

        return $this->complete($user, $session);
    }

    /** Re-authentication for high-risk actions (FR-SEC-013). Returns the step-up reference. */
    public function stepUp(User $user, string $password, ?string $code, Session $session): string
    {
        $now = $this->clock->now();
        $ok = $user->password !== null && $this->hasher->check($password, $user->password);
        if ($ok && $user->mfa_confirmed_at !== null) {
            $step = $code === null ? null : $this->checkTotp($user, $code, $now);
            $ok = $step !== null;
            if ($step !== null) {
                $user->mfa_last_used_step = $step;
                $user->save();
            }
        }
        if (! $ok) {
            $this->registerFailure($user, 'step_up_failed', 'auth.step_up.failed');
            throw new AuthenticationFailed('Re-authentication failed.');
        }
        $ref = UuidV7::generate();
        $session->put('auth.step_up_at', $now->getTimestamp());
        $session->put('auth.step_up_ref', $ref);
        $this->auditUser($user, 'auth.step_up.succeeded', AuditOutcome::Success, ['step_up_ref' => $ref]);

        return $ref;
    }

    public function logout(User $user, Session $session): void
    {
        $this->auditUser($user, 'auth.logout', AuditOutcome::Success, []);
        $this->guard->logout();
        $session->invalidate();
        $session->regenerateToken();
    }

    private function complete(User $user, Session $session): LoginResult
    {
        $now = $this->clock->now();
        $this->guard->login($user);
        $session->regenerate();
        $session->put('auth.tenant_id', $user->tenant_id);
        $session->put('auth.at', $now->getTimestamp());
        $session->put('auth.last_activity', $now->getTimestamp());
        $session->put('auth.version', $user->auth_version);
        // A fresh primary authentication counts as a step-up.
        $session->put('auth.step_up_at', $now->getTimestamp());
        $session->put('auth.step_up_ref', UuidV7::generate());

        $user->last_login_at = Carbon::instance($now);
        $user->failed_login_count = 0;
        $user->save();

        $evicted = $this->enforceConcurrentCap($user, $session->getId());
        $this->principal->set(new Principal($user->id, $user->tenant_id, PrincipalKind::Human));
        $this->audit->record(new AuditEntry(
            action: 'auth.login.succeeded',
            entityType: 'user',
            entityId: $user->id,
            after: ['sessions_evicted' => $evicted],
        ));

        return LoginResult::authenticated($user->id);
    }

    /** Keeps at most max_concurrent sessions: the new one plus the most recent others. */
    private function enforceConcurrentCap(User $user, string $currentSessionId): int
    {
        $cap = max(1, $this->sessionPolicy->current()->maxConcurrent);
        /** @var list<string> $others */
        $others = $this->db->table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)
            ->orderByDesc('last_activity')->pluck('id')->all();
        $evict = array_slice($others, $cap - 1);
        if ($evict !== []) {
            $this->db->table('sessions')->whereIn('id', $evict)->delete();
        }

        return count($evict);
    }

    private function checkTotp(User $user, string $code, DateTimeImmutable $now): ?int
    {
        if ($user->mfa_secret === null) {
            return null;
        }
        $secret = $this->tenant->run($user->tenant_id, fn (): string => $this->encryptor->decrypt((string) $user->mfa_secret, self::MFA_FIELD));

        return Totp::verify($secret, $code, $now->getTimestamp(), $user->mfa_last_used_step);
    }

    private function registerFailure(User $user, string $reason, string $action = 'auth.login.failed'): void
    {
        $policy = new LockoutPolicy(
            (int) config('fundly.auth.lockout.threshold', 5),
            (int) config('fundly.auth.lockout.base_minutes', 1),
            (int) config('fundly.auth.lockout.max_minutes', 60),
        );
        $user->failed_login_count++;
        $lockUntil = $policy->lockUntil($user->failed_login_count, $this->clock->now());
        $user->locked_until = $lockUntil === null ? null : Carbon::instance($lockUntil);
        $user->save();

        $this->auditUser($user, $action, AuditOutcome::Failure, ['reason' => $reason, 'consecutive_failures' => $user->failed_login_count]);
        if ($lockUntil !== null) {
            $this->auditUser($user, 'auth.account.locked', AuditOutcome::Success, ['locked_until' => $lockUntil->format(DATE_ATOM)]);
        }
    }

    private function assertLicenceAllowsLogin(User $user): void
    {
        $now = $this->clock->now();
        $permissions = [];
        foreach ($this->grants->forUser($user->id) as $g) {
            if ($g->isActiveAt($now)) {
                foreach ($g->permissions as $p) {
                    $permissions[$p] = true;
                }
            }
        }
        $readOnly = $permissions !== [];
        foreach (array_keys($permissions) as $p) {
            if (Permission::tryFrom($p)?->isReadOnly() !== true && $p !== Permission::AuditVerify->value) {
                $readOnly = false;
            }
        }
        $activeNamed = User::query()->where('kind', User::KIND_HUMAN)->where('status', 'active')->count();

        try {
            $this->licence->assertLoginAllowed($activeNamed, $readOnly, isset($permissions[Permission::UserManage->value]));
        } catch (ProblemException $e) {
            $this->auditUser($user, 'auth.login.denied_by_licence', AuditOutcome::Denied, ['reason' => $e->type()]);
            throw $e;
        }
    }

    /** A real hash with current parameters, so unknown users cost the same as known ones. */
    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= $this->hasher->make(bin2hex(random_bytes(16)));
    }

    /** @param array<string, mixed> $details */
    private function auditUser(User $user, string $action, AuditOutcome $outcome, array $details): void
    {
        $this->tenant->run($user->tenant_id, function () use ($user, $action, $outcome, $details): void {
            $previous = $this->principal->get();
            $this->principal->set(new Principal($user->id, $user->tenant_id, PrincipalKind::Human));
            try {
                $this->audit->record(new AuditEntry(action: $action, outcome: $outcome, entityType: 'user', entityId: $user->id, after: $details), $this->actors->current());
            } finally {
                $this->principal->set($previous);
            }
        });
    }

    /** @param array<string, mixed> $details */
    private function auditAnonymous(string $action, array $details): void
    {
        if (! $this->tenant->has()) {
            return;
        }
        if (isset($details['email']) && is_string($details['email'])) {
            $details['email'] = PiiMasker::maskValue($details['email']);
        }
        $this->audit->record(new AuditEntry(action: $action, outcome: AuditOutcome::Failure, after: $details), Actor::anonymous());
    }
}
