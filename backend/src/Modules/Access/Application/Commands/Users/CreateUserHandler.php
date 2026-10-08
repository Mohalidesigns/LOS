<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use Fundly\Modules\Access\Application\Support\UserPresenter;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Licensing\Contracts\LicenceEntitlements;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\ValidationFailed;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;

final class CreateUserHandler implements CommandHandler
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly LicenceEntitlements $licence,
        private readonly ConnectionInterface $db,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateUser);
        if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($command->email)])->exists()) {
            throw new CodedConflict('user-email-taken', 'A user with this email already exists.');
        }
        if ($command->kind === User::KIND_HUMAN) {
            $this->licence->assertCanAddNamedUser(User::query()->where('kind', User::KIND_HUMAN)->where('status', 'active')->count());
        }
        if ($command->homeOrgUnitId !== null) {
            $ou = $this->db->table('org_units')->where('id', $command->homeOrgUnitId)->first();
            if ($ou === null || ($command->homeLegalEntityId !== null && $ou->legal_entity_id !== $command->homeLegalEntityId)) {
                throw ValidationFailed::with(['home_org_unit_id' => 'Org unit does not exist in the home legal entity.']);
            }
        }

        $user = new User;
        $user->forceFill([
            'kind' => $command->kind,
            'email' => $command->email,
            'name' => $command->name,
            'password' => $command->password === null ? null : $this->hasher->make($command->password),
            'password_changed_at' => $command->password === null ? null : $this->clock->now(),
            'status' => 'active',
            'home_legal_entity_id' => $command->homeLegalEntityId ?? ($command->homeOrgUnitId !== null ? $this->db->table('org_units')->where('id', $command->homeOrgUnitId)->value('legal_entity_id') : null),
            'home_org_unit_id' => $command->homeOrgUnitId,
        ])->save();
        $user->refresh();

        $context->audit(new AuditEntry(action: $command->action(), entityType: 'user', entityId: $user->id, after: UserPresenter::present($user)));

        return ['data' => UserPresenter::present($user), 'etag' => UserPresenter::etag($user)];
    }
}
