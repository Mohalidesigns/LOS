<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Modules\Disbursement\Application\ReleaseDisbursementAction;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Exceptions\DomainRuleViolation;

/**
 * Same instruction, same step keys: a retry can only complete what is
 * missing, never post again (FR-DSB-005/007). A compensated instruction is
 * dead; it needs a new one and a new checker (D-040b).
 */
final class RetryDisbursementHandler implements CommandHandler
{
    public function __construct(private readonly DisbursementFile $file, private readonly DisbursementQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RetryDisbursement);
        $d = $this->file->disbursement($command->disbursementId);
        if (! DisbursementStatus::from($d->status)->canRetry()) {
            throw new DomainRuleViolation("An instruction that is {$d->status} cannot be retried.");
        }
        $before = $d->status;
        // intervention cases are re-run explicitly; retryable ones may still be in the outbox's backoff
        $d->forceFill(['status' => DisbursementStatus::FailedRetryable->value])->save();
        $context->outbox(new OutboxIntent(
            topic: ReleaseDisbursementAction::SAGA_TOPIC,
            payload: ['disbursement_id' => $d->id],
            aggregateType: 'disbursement',
            aggregateId: $d->id,
            idempotencyKey: "{$context->principal->tenantId}:dsb-book:{$d->id}:retry-".($d->saga_runs + 1),
            maxAttempts: 8,
        ));
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'disbursement', entityId: $d->id, before: ['status' => $before], after: ['status' => $d->status, 'last_error_code' => $d->last_error_code]));

        return ['data' => $this->queries->present($d)];
    }
}
