<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationBreak;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;

final class ResolveBreakHandler implements CommandHandler
{
    public function __construct(private readonly DisbursementFile $file, private readonly DisbursementQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ResolveBreak);
        $b = ReconciliationBreak::query()->find($command->breakId) ?? throw new NotFound('Break not found.');
        if ($b->status !== 'open') {
            throw new DomainRuleViolation('This break is already resolved.');
        }
        $b->forceFill(['status' => 'resolved', 'resolved_by' => $context->principal->id, 'resolved_at' => $this->file->now(), 'resolution_note' => $command->note])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'reconciliation_break', entityId: $b->id, after: ['kind' => $b->kind, 'posting_ref' => $b->posting_ref], reasonText: $command->note));

        return ['data' => $this->queries->breakRow($b)];
    }
}
