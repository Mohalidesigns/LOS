<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Application\ApprovalFile;
use Fundly\Modules\Approval\Domain\ApprovalChain;
use Fundly\Modules\Approval\Domain\MatrixDefinition;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class OpenApprovalRequestHandler implements CommandHandler
{
    private const DEFAULT_VALIDITY_DAYS = 30;

    public function __construct(private readonly ApprovalFile $file, private readonly CreditFile $credit, private readonly ApplicationLifecycle $lifecycle) {}

    public function handle(Command $command, CommandContext $context): string
    {
        assert($command instanceof OpenApprovalRequest);
        $app = $this->file->application($command->applicationId);
        if ($app->status !== CanonicalStatus::Recommended) {
            throw new DomainRuleViolation("Only a recommended application can be routed for approval; this one is {$app->status->value}.");
        }
        if (ApprovalRequest::query()->where('application_id', $app->id)->where('status', 'pending')->exists()) {
            throw new DomainRuleViolation('This application already has an open approval request.');
        }
        $product = $this->file->product($app);
        $version = $this->file->activeMatrix($product);
        $matrix = MatrixDefinition::of($version->content);
        $exposure = $this->file->exposure($app);
        if ($exposure->currency->code !== $matrix->currency()) {
            throw new DomainRuleViolation("The approval matrix is in {$matrix->currency()} but the application is in {$exposure->currency->code}.");
        }
        $decision = $this->credit->latestDecision($app->id);
        $facts = $this->file->facts($app, $product, $decision, $exposure);
        $routed = $matrix->route($facts);
        if ($routed['levels'] === []) {
            throw new DomainRuleViolation('No row of the approval matrix matched this application; the matrix needs a catch-all row.');
        }
        $limits = $this->file->limits($matrix->currency());
        $excluded = array_values(array_unique([$app->originatorId, $command->recommendedBy]));
        $levels = ApprovalChain::build(
            $matrix,
            $routed['levels'],
            $matrix->exceptionEscalates($decision->exceptionCount ?? 0, $decision?->maxExceptionSeverity),
            fn (array $level): bool => $this->file->staffed($level, $app, $exposure, $limits, $excluded),
        );

        // an earlier approval (e.g. before the customer renegotiated, G-14d) no longer stands
        ApprovalRequest::query()->where('application_id', $app->id)->where('status', 'approved')->update(['status' => 'superseded']);

        $r = new ApprovalRequest;
        $r->forceFill([
            'application_id' => $app->id,
            'sequence' => (int) ApprovalRequest::query()->where('application_id', $app->id)->max('sequence') + 1,
            'status' => 'pending',
            'matrix_key' => $version->key,
            'matrix_version_id' => $version->id,
            'matrix_version_no' => $version->versionNo,
            'matrix_row' => $routed['row'],
            'facts' => $facts,
            'routing_trace' => $routed['trace'],
            'levels' => $levels,
            'current_level' => 0,
            'decision_id' => $decision?->decisionId,
            'exposure_amount' => (string) $exposure->amount,
            'exposure_currency' => $exposure->currency->code,
            'recommended_by' => $command->recommendedBy,
            'opened_at' => $this->file->now(),
            'validity_days' => $matrix->validityDays() ?? (is_int($product->content['approval_validity_days'] ?? null) ? $product->content['approval_validity_days'] : self::DEFAULT_VALIDITY_DAYS),
        ])->save();

        $chain = implode(' → ', array_column($levels, 'code'));
        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'approval_request',
            entityId: $r->id,
            after: ['application_id' => $app->id, 'matrix' => ['key' => $version->key, 'version_no' => $version->versionNo, 'row' => $routed['row']], 'levels' => $levels, 'exposure' => $exposure->toArray()],
        ));
        $this->lifecycle->advance($app->id, CanonicalStatus::Approval, 'APPROVAL_ROUTED', "Approval chain {$chain} (matrix {$version->key} v{$version->versionNo}, row {$routed['row']})", $context->principal);

        return $r->id;
    }
}
