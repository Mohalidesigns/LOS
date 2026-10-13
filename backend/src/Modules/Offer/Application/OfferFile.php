<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Offer\Domain\OfferTemplate;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Product\Contracts\ProductView;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/** Shared lookups for offer commands and queries. */
final class OfferFile
{
    public const TEMPLATE_TYPE = 'offer.template';

    public const PERMISSION = 'offer:prepare';

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

    public function offer(string $id): Offer
    {
        $o = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Offer::query()->find($id) : null;

        return $o ?? throw new NotFound('Offer not found.');
    }

    public function product(ApplicationSummary $app): ProductView
    {
        return $this->products->version($app->productVersionId) ?? throw new DomainRuleViolation('The pinned product version is not readable.');
    }

    /** The active version of the template the pinned product binds (FR-OFR-001). */
    public function activeTemplate(ProductView $product): ConfigVersionView
    {
        $key = $product->binding('offer_template') ?? throw new DomainRuleViolation("Product {$product->key} binds no offer template.");

        return $this->config->activeVersion(self::TEMPLATE_TYPE, $key) ?? throw new DomainRuleViolation("Offer template '{$key}' has no active version.");
    }

    public function templateOf(Offer $offer): OfferTemplate
    {
        $v = $this->config->version($offer->template_version_id) ?? throw new DomainRuleViolation('The template version of this offer is not readable.');

        return OfferTemplate::of($v->content);
    }

    public function resource(ApplicationSummary $app, ?string $entityType = null, ?string $entityId = null): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: $entityType ?? 'application', entityId: $entityId ?? $app->id);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }
}
