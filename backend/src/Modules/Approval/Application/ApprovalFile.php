<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Access\Contracts\AuthorityGrant;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Approval\Domain\ApproverEligibility;
use Fundly\Modules\Approval\Domain\AuthorityLimits;
use Fundly\Modules\Approval\Domain\MatrixDefinition;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalVote;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Credit\Contracts\CreditDecisionView;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Product\Contracts\ProductView;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/** Shared lookups for approval commands and queries. */
final class ApprovalFile
{
    public const MATRIX_TYPE = 'approval.matrix';

    public const LIMITS_TYPE = 'approval.authority_limits';

    public const LIMITS_KEY = 'default';

    public const PERMISSION = 'application:approve';

    /** @var array<string, list<string>> */
    private array $subtrees = [];

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly ActiveConfiguration $config,
        private readonly AuthorityDirectory $authority,
        private readonly OrganisationDirectory $organisation,
        private readonly Clock $clock,
        private readonly CollateralRegister $collateral,
    ) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function request(string $id): ApprovalRequest
    {
        $r = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ApprovalRequest::query()->find($id) : null;

        return $r ?? throw new NotFound('Approval request not found.');
    }

    public function product(ApplicationSummary $app): ProductView
    {
        return $this->products->version($app->productVersionId) ?? throw new DomainRuleViolation('The pinned product version is not readable.');
    }

    /** The active version of the matrix the pinned product binds (FR-APV-001). */
    public function activeMatrix(ProductView $product): ConfigVersionView
    {
        $key = $product->binding('approval_matrix') ?? throw new DomainRuleViolation("Product {$product->key} binds no approval matrix.");

        return $this->config->activeVersion(self::MATRIX_TYPE, $key) ?? throw new DomainRuleViolation("Approval matrix '{$key}' has no active version.");
    }

    /** The exact matrix version a request was routed with. */
    public function matrixOf(ApprovalRequest $r): MatrixDefinition
    {
        $v = $this->config->version($r->matrix_version_id) ?? throw new DomainRuleViolation('The approval matrix version of this request is not readable.');

        return MatrixDefinition::of($v->content);
    }

    public function limits(string $currency): AuthorityLimits
    {
        $content = $this->config->content(self::LIMITS_TYPE, self::LIMITS_KEY);

        return $content === null ? AuthorityLimits::none($currency) : AuthorityLimits::of($content);
    }

    /** Authority is driven by total application exposure (G-15): the requested amount in P1. */
    public function exposure(ApplicationSummary $app): Money
    {
        return $app->requestedAmount ?? throw new DomainRuleViolation('The application has no requested amount.');
    }

    /** @return array<string, mixed> facts for the routing table (TRD §6.3 inputs) */
    public function facts(ApplicationSummary $app, ProductView $product, ?CreditDecisionView $credit, Money $exposure): array
    {
        return [
            'application' => ['amount' => (string) $exposure->amount, 'currency' => $exposure->currency->code, 'tenor_months' => $app->tenorMonths, 'segment' => $app->segment, 'channel' => $app->channel],
            'product' => ['key' => $product->key, 'category' => $product->category, 'segment' => $product->segment],
            'decision' => $credit === null ? ['outcome' => null, 'risk_grade' => null] : ['outcome' => $credit->outcome, 'risk_grade' => $credit->riskGrade],
            'exceptions' => ['count' => $credit->exceptionCount ?? 0, 'max_severity' => $credit?->maxExceptionSeverity],
            'exposure' => ['amount' => (string) $exposure->amount, 'currency' => $exposure->currency->code],
            // security cover from allocated collateral (FR-COL-002); insider status (FR-CMP-024) is P3
            'collateral' => ['coverage_percent' => $this->collateral->coverage($app->id)?->coverPercent],
            'customer' => ['insider' => false],
        ];
    }

    public function resource(ApplicationSummary $app): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id);
    }

    /**
     * The user's approval authorities for this application, each with its effective limit.
     *
     * @return list<array{assignment_id: string, role_code: string, role_name: string, delegation_id: ?string, delegated_by: ?string, limit: ?Money, limit_source: ?string, limit_components: list<array<string, mixed>>}>
     */
    public function authorities(string $userId, ApplicationSummary $app, AuthorityLimits $limits): array
    {
        return $this->withLimits($this->authority->grantsFor($userId, self::PERMISSION, $this->resource($app)), $limits);
    }

    /**
     * Eligibility of one user at the request's current level.
     *
     * @return array{eligible: bool, blockers: list<string>, basis: ?array<string, mixed>}
     */
    public function eligibility(string $userId, ApprovalRequest $r, ApplicationSummary $app): array
    {
        $level = $r->levels[$r->current_level] ?? null;
        if ($r->status !== 'pending' || $level === null) {
            return ['eligible' => false, 'blockers' => ['This approval request is no longer open.'], 'basis' => null];
        }
        $exposure = Money::of($r->exposure_amount, $r->exposure_currency);

        return ApproverEligibility::assess($userId, $app->originatorId, $r->recommended_by, $this->priorVoters($r), $level['roles'], $this->authorities($userId, $app, $this->limits($exposure->currency->code)), $exposure);
    }

    /**
     * Is anyone (other than people barred by SoD) able to approve a level for this exposure?
     *
     * @param  array{code: string, name: string, roles: list<string>, mode: string}  $level
     * @param  list<string>  $excluded
     */
    public function staffed(array $level, ApplicationSummary $app, Money $exposure, AuthorityLimits $limits, array $excluded): bool
    {
        $byUser = [];
        foreach ($this->authority->holders(self::PERMISSION, $this->resource($app)) as $g) {
            if (! in_array($g->userId, $excluded, true)) {
                $byUser[$g->userId][] = $g;
            }
        }
        foreach ($byUser as $userId => $grants) {
            if (ApproverEligibility::assess((string) $userId, $app->originatorId, null, $excluded, $level['roles'], $this->withLimits($grants, $limits), $exposure)['eligible']) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function priorVoters(ApprovalRequest $r): array
    {
        return array_values(array_map('strval', ApprovalVote::query()->where('request_id', $r->id)->pluck('voter_id')->all()));
    }

    public function stepUpMinutes(): int
    {
        return (int) config('fundly.auth.step_up_default_minutes', 5);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }

    /**
     * @param  list<AuthorityGrant>  $grants
     * @return list<array{assignment_id: string, role_code: string, role_name: string, delegation_id: ?string, delegated_by: ?string, limit: ?Money, limit_source: ?string, limit_components: list<array<string, mixed>>}>
     */
    private function withLimits(array $grants, AuthorityLimits $limits): array
    {
        $out = [];
        foreach ($grants as $g) {
            $users = array_values(array_filter([$g->userId, $g->delegatedBy]));
            $orgs = $g->homeOrgUnitId === null ? [] : array_values(array_filter($limits->orgUnitIds(), fn (string $o): bool => in_array($g->homeOrgUnitId, $this->subtree($o), true)));
            $e = $limits->effective($g->roleCode, $g->scopeMaxAmount, $users, $orgs);
            $out[] = [
                'assignment_id' => $g->assignmentId, 'role_code' => $g->roleCode, 'role_name' => $g->roleName,
                'delegation_id' => $g->delegationId, 'delegated_by' => $g->delegatedBy,
                'limit' => $e['limit'], 'limit_source' => $e['source'], 'limit_components' => $e['components'],
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private function subtree(string $orgUnitId): array
    {
        return $this->subtrees[$orgUnitId] ??= $this->organisation->subtree($orgUnitId);
    }
}
