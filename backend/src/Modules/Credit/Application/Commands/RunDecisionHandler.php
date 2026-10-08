<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Application\CreditPresenter;
use Fundly\Modules\Credit\Application\FactAssembler;
use Fundly\Modules\Credit\Domain\DecisionFlow;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Rules\EvaluatorRegistry;
use Illuminate\Support\Facades\DB;

final class RunDecisionHandler implements CommandHandler
{
    public function __construct(private readonly CreditFileLoader $file, private readonly FactAssembler $facts) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RunDecision);
        $app = $this->file->application($command->applicationId);
        if ($app->status !== CanonicalStatus::Assessment) {
            throw new DomainRuleViolation("Credit decisions run in Assessment; this application is {$app->status->value}.");
        }
        $product = $this->file->product($app);
        $ruleSet = $this->file->ruleSet($product);
        // FR-CMP-020: no decision without a current bureau report on the primary applicant.
        $bureau = $this->file->validBureau($app->id, $this->file->primaryPartyId($app))
            ?? throw new DomainRuleViolation('Pull a current credit bureau report for the primary applicant before running the decision (FR-CMP-020).');

        $evaluator = is_string($ruleSet->content['evaluator_version'] ?? null) ? $ruleSet->content['evaluator_version'] : EvaluatorRegistry::CURRENT;
        $result = DecisionFlow::run($ruleSet->content, $this->facts->assemble($app, $product, $app->data, $bureau), $evaluator);

        // Serialise sequence numbers per application (FOR UPDATE cannot be combined with max()).
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['decision:'.$app->id]);
        $snapshot = new DecisionSnapshot;
        $snapshot->forceFill([
            'application_id' => $app->id,
            'sequence' => (int) DecisionSnapshot::query()->where('application_id', $app->id)->max('sequence') + 1,
            'outcome' => $result['outcome'],
            'risk_grade' => $result['risk_grade'],
            'rule_set_version_id' => $ruleSet->id,
            'rule_set_key' => $ruleSet->key,
            'rule_set_version_no' => $ruleSet->versionNo,
            'evaluator_version' => $evaluator,
            'bureau_report_id' => $bureau->id,
            'facts' => $result['facts'],
            'outputs' => [
                'outcome' => $result['outcome'], 'risk_grade' => $result['risk_grade'], 'reason_codes' => $result['reason_codes'],
                'requested_terms' => $result['requested_terms'], 'recommended_terms' => $result['recommended_terms'], 'affordability' => $result['affordability'],
            ],
            'trace' => $result['trace'],
            'decided_by' => $context->principal->id,
            'decided_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: [
            'decision_id' => $snapshot->id, 'outcome' => $result['outcome'], 'risk_grade' => $result['risk_grade'],
            'reason_codes' => array_column($result['reason_codes'], 'code'), 'rule_set_version_id' => $ruleSet->id, 'evaluator_version' => $evaluator,
        ]));

        return ['data' => CreditPresenter::decision($snapshot->refresh(), [], true)];
    }
}
