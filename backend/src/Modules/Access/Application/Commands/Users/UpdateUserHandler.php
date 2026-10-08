<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use Fundly\Modules\Access\Application\SessionRevoker;
use Fundly\Modules\Access\Application\Support\UserPresenter;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Licensing\Contracts\LicenceEntitlements;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ETag;
use Illuminate\Database\ConnectionInterface;

final class UpdateUserHandler implements CommandHandler
{
    public function __construct(
        private readonly SessionRevoker $sessions,
        private readonly LicenceEntitlements $licence,
        private readonly ConnectionInterface $db,
    ) {}

    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdateUser);
        $user = User::query()->lockForUpdate()->findOrFail($command->userId);
        ETag::assertHeader($command->ifMatch, UserPresenter::etag($user));
        $before = UserPresenter::present($user);
        $changes = $command->changes;

        if (isset($changes['status']) && $changes['status'] === 'disabled' && $user->id === $context->principal->id) {
            throw new DomainRuleViolation('You cannot disable your own account.');
        }
        if (isset($changes['status']) && $changes['status'] === 'active' && $user->status !== 'active' && $user->kind === User::KIND_HUMAN) {
            $this->licence->assertCanAddNamedUser(User::query()->where('kind', User::KIND_HUMAN)->where('status', 'active')->count());
        }
        if (array_key_exists('home_org_unit_id', $changes) && is_string($changes['home_org_unit_id'])) {
            $le = $this->db->table('org_units')->where('id', $changes['home_org_unit_id'])->value('legal_entity_id');
            if ($le === null) {
                throw ValidationFailed::with(['home_org_unit_id' => 'Org unit does not exist.']);
            }
            $user->home_legal_entity_id = (string) $le;
        }

        foreach (['name', 'status', 'home_org_unit_id'] as $field) {
            if (array_key_exists($field, $changes)) {
                $user->setAttribute($field, $changes[$field]);
            }
        }
        $statusChanged = $user->isDirty('status');
        $user->save();

        if ($statusChanged && $user->status === 'disabled') {
            $this->sessions->revokeAll($user->id);
            $user->tokens()->delete();
        }

        $context->audit(new AuditEntry(action: $command->action(), entityType: 'user', entityId: $user->id, before: $before, after: UserPresenter::present($user)));

        return ['data' => UserPresenter::present($user), 'etag' => UserPresenter::etag($user)];
    }
}
