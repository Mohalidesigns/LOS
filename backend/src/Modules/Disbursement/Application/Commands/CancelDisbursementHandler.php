<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

/** The pending change request then fails validation if a checker tries it: the instruction is no longer pending. */
final class CancelDisbursementHandler implements CommandHandler
{
    public function __construct(private readonly DisbursementFile $file, private readonly DisbursementQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CancelDisbursement);
        $d = $this->file->disbursement($command->disbursementId);
        if ($d->status !== DisbursementStatus::PendingRelease->value) {
            throw new DomainRuleViolation("Only an unreleased instruction can be cancelled; this one is {$d->status}.");
        }
        $d->forceFill(['status' => DisbursementStatus::Cancelled->value, 'cancelled_by' => $context->principal->id, 'cancelled_at' => $this->file->now(), 'cancel_reason' => $command->reason])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'disbursement', entityId: $d->id, after: ['status' => $d->status], reasonText: $command->reason));

        return ['data' => $this->queries->present($d)];
    }
}
