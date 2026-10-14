<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Collateral\Domain\CollateralPolicy;
use Fundly\Modules\Collateral\Domain\CollateralType;
use Fundly\Modules\Collateral\Domain\DefaultCollateralTypes;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/** Shared lookups for collateral commands and queries. */
final class CollateralFile
{
    public const TYPE = 'collateral.type';

    /** Applications whose security is no longer worked. */
    private const CLOSED = [CanonicalStatus::Declined, CanonicalStatus::Expired, CanonicalStatus::Withdrawn, CanonicalStatus::Cancelled];

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly ActiveConfiguration $config,
        private readonly Clock $clock,
    ) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function collateral(string $id): Collateral
    {
        $c = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Collateral::query()->find($id) : null;

        return $c ?? throw new NotFound('Collateral not found.');
    }

    public function step(string $id): PerfectionStep
    {
        $s = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? PerfectionStep::query()->find($id) : null;

        return $s ?? throw new NotFound('Perfection step not found.');
    }

    /**
     * The tenant's active version of a type (maker-checker activated), else the built-in default.
     *
     * @return array{0: CollateralType, 1: 'tenant'|'default', 2: ?string}
     */
    public function type(string $code): array
    {
        $version = $this->config->activeVersion(self::TYPE, $code);
        if ($version !== null) {
            return [CollateralType::of($version->content), 'tenant', $version->id];
        }
        $default = DefaultCollateralTypes::all()[$code] ?? null;
        if ($default === null) {
            throw ValidationFailed::with(['type_code' => 'Unknown collateral type. Available: '.implode(', ', array_keys($this->types())).'.']);
        }

        return [CollateralType::of($default), 'default', null];
    }

    /** @return array<string, array{type: CollateralType, source: 'tenant'|'default', version_id: ?string}> every type the tenant can capture */
    public function types(): array
    {
        $out = [];
        foreach (DefaultCollateralTypes::all() as $code => $content) {
            $out[$code] = ['type' => CollateralType::of($content), 'source' => 'default', 'version_id' => null];
        }
        foreach ($this->config->activeVersions(self::TYPE) as $v) {
            $out[$v->key] = ['type' => CollateralType::of($v->content), 'source' => 'tenant', 'version_id' => $v->id];
        }
        ksort($out);

        return $out;
    }

    public function policy(ApplicationSummary $app): CollateralPolicy
    {
        return CollateralPolicy::fromProduct($this->products->version($app->productVersionId)->content ?? []);
    }

    /** Facility exposure for cover: the requested amount in P1, as for approval authority (G-15). */
    public function exposure(ApplicationSummary $app): Money
    {
        return $app->requestedAmount ?? throw new DomainRuleViolation('The application has no requested amount.');
    }

    public function assertOpen(ApplicationSummary $app): void
    {
        if (in_array($app->status, self::CLOSED, true)) {
            throw new DomainRuleViolation("Security is not worked on a {$app->status->value} application.");
        }
    }

    public function resource(ApplicationSummary $app, string $type, string $id): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: $type, entityId: $id);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }

    public function today(): Carbon
    {
        return $this->now()->startOfDay();
    }

    public function noticeDays(): int
    {
        return max(1, (int) config('fundly.collateral.notice_days', 30));
    }
}
