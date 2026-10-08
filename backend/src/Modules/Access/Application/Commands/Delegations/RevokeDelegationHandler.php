<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Delegations;

use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Modules\Access\Application\SessionRevoker;
use Fundly\Modules\Access\Infrastructure\Models\Delegation;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class RevokeDelegationHandler implements CommandHandler
{
    public function __construct(private readonly Clock $clock, private readonly SessionRevoker $sessions)
    {
    }

    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RevokeDelegation);
        $d = Delegation::query()->findOrFail($command->delegationId);
        if ($d->delegator_id !== $context->principal->id) {
            throw new DomainRuleViolation('Only the delegator can revoke a delegation.');
        }
        if ($d->revoked_at !== null) {
            throw new DomainRuleViolation('The delegation is already revoked.');
        }
        $d->forceFill(['revoked_at' => $this->clock->now(), 'revoked_by' => $context->principal->id])->save();
        $this->sessions->revokeAll($d->delegate_id);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'delegation', entityId: $d->id, after: ['revoked_at' => $d->revoked_at?->toIso8601ZuluString()]));

        return ['data' => AccessQueries::presentDelegation($d)];
    }
}
