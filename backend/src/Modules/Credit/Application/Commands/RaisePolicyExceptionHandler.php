<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Application\CreditPresenter;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Credit\Infrastructure\Models\PolicyException;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;

final class RaisePolicyExceptionHandler implements CommandHandler
{
    public function __construct(private readonly CreditFileLoader $file) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RaisePolicyException);
        $d = DecisionSnapshot::query()->find($command->decisionId) ?? throw new NotFound('Decision not found.');
        if ($this->file->latestDecision($d->application_id)?->id !== $d->id) {
            throw new DomainRuleViolation('Exceptions are raised against the latest decision.');
        }
        $codes = array_column((array) ($d->outputs['reason_codes'] ?? []), 'code');
        if (! in_array($command->reasonCode, $codes, true)) {
            throw ValidationFailed::with(['reason_code' => 'Choose a reason code produced by this decision: '.implode(', ', $codes).'.']);
        }
        $e = new PolicyException;
        $e->forceFill([
            'application_id' => $d->application_id,
            'decision_id' => $d->id,
            'reason_code' => $command->reasonCode,
            'justification' => (string) $command->justification,
            'evidence_ref' => $command->evidenceRef,
            'severity' => $command->severity,
            'raised_by' => $context->principal->id,
            'raised_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'decision', entityId: $d->id, after: ['exception_id' => $e->id, 'reason_code' => $e->reason_code, 'severity' => $e->severity, 'evidence_ref' => $e->evidence_ref], reasonText: $e->justification));

        return ['data' => CreditPresenter::exception($e)];
    }
}
