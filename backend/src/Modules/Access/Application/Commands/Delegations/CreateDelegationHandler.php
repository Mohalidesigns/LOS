<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Delegations;

use DateTimeImmutable;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Modules\Access\Application\SodChecker;
use Fundly\Modules\Access\Contracts\SodConflict;
use Fundly\Modules\Access\Infrastructure\Models\Delegation;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class CreateDelegationHandler implements CommandHandler
{
    public function __construct(private readonly SodChecker $sod, private readonly GrantRepository $grants) {}

    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateDelegation);
        $assignment = RoleAssignment::query()->findOrFail($command->roleAssignmentId);
        if ($assignment->user_id !== $context->principal->id) {
            throw new DomainRuleViolation('You can only delegate your own role assignments.');
        }
        if ($assignment->revoked_at !== null) {
            throw new DomainRuleViolation('The assignment has been revoked.');
        }
        $delegate = User::query()->find($command->delegateId);
        if (! $delegate instanceof User || ! $delegate->isActive() || $delegate->isService()) {
            throw ValidationFailed::with(['delegate_id' => 'The delegate must be an active staff user.']);
        }
        if ($delegate->id === $context->principal->id) {
            throw ValidationFailed::with(['delegate_id' => 'You cannot delegate to yourself.']);
        }

        $from = new DateTimeImmutable($command->validFrom);
        $to = new DateTimeImmutable((string) $command->validTo);
        $maxDays = (int) config('fundly.delegation.max_days', 30);
        if ($to->getTimestamp() - $from->getTimestamp() > $maxDays * 86400) {
            throw ValidationFailed::with(['valid_to' => "A delegation may last at most {$maxDays} days."]);
        }
        if ($assignment->valid_to !== null && $to > $assignment->valid_to->toDateTimeImmutable()) {
            throw ValidationFailed::with(['valid_to' => 'A delegation cannot outlive the delegated assignment.']);
        }

        $role = Role::query()->findOrFail($assignment->role_id);
        $conflicts = $this->sod->conflictsFor($delegate->id, $role->permissionCodes(), [$role->id]);
        if ($conflicts !== []) {
            throw new SodConflict($conflicts);
        }

        $delegation = new Delegation;
        $delegation->forceFill([
            'delegator_id' => $context->principal->id,
            'delegate_id' => $delegate->id,
            'role_assignment_id' => $assignment->id,
            'reason' => $command->reason,
            'valid_from' => $from,
            'valid_to' => $to,
        ])->save();
        $this->grants->forget($delegate->id);

        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'delegation',
            entityId: $delegation->id,
            after: ['delegator_id' => $context->principal->id, 'delegate_id' => $delegate->id, 'role_assignment_id' => $assignment->id, 'role_code' => $role->code, 'valid_from' => $from->format(DATE_ATOM), 'valid_to' => $to->format(DATE_ATOM)],
            reasonText: $command->reason,
        ));

        return ['data' => AccessQueries::presentDelegation($delegation)];
    }
}
