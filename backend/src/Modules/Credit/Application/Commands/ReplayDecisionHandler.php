<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Modules\Credit\Domain\DecisionFlow;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Json\CanonicalJson;

final class ReplayDecisionHandler implements CommandHandler
{
    private const COMPARED = ['outcome', 'risk_grade', 'reason_codes', 'requested_terms', 'recommended_terms', 'affordability'];

    public function __construct(private readonly ActiveConfiguration $config) {}

    /** @return array{data: array{identical: bool, evaluator_version: string, rule_set_version_id: string, differences: list<array{path: string, recorded: mixed, replayed: mixed}>}} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ReplayDecision);
        $d = DecisionSnapshot::query()->find($command->decisionId) ?? throw new NotFound('Decision not found.');
        $ruleSet = $this->config->version($d->rule_set_version_id) ?? throw new NotFound('The recorded rule set version is missing.');
        // Replay from the *stored* facts: strip the derived parts the flow recomputes.
        $facts = $d->facts;
        unset($facts['formulas'], $facts['decision']);
        $replayed = DecisionFlow::run($ruleSet->content, $facts, $d->evaluator_version);
        $diffs = [];
        foreach (self::COMPARED as $key) {
            $a = $d->outputs[$key] ?? null;
            $b = $replayed[$key];
            if (CanonicalJson::encode(['v' => $a]) !== CanonicalJson::encode(['v' => $b])) {
                $diffs[] = ['path' => $key, 'recorded' => $a, 'replayed' => $b];
            }
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'decision', entityId: $d->id, after: ['identical' => $diffs === [], 'evaluator_version' => $d->evaluator_version, 'rule_set_version_id' => $d->rule_set_version_id]));

        return ['data' => ['identical' => $diffs === [], 'evaluator_version' => $d->evaluator_version, 'rule_set_version_id' => $d->rule_set_version_id, 'differences' => $diffs]];
    }
}
