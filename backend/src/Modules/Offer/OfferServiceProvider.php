<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer;

use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Audit\Contracts\EvidenceSources;
use Fundly\Modules\Offer\Application\Commands\DeliverOfferHandler;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Console\ExpireOffersCommand;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Offer\Domain\OfferTemplate;
use Fundly\Modules\Offer\Infrastructure\DatabaseOfferRegister;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Modules\Offer\Infrastructure\Models\OfferDelivery;
use Fundly\Modules\Offer\Infrastructure\SendOfferEmailOutboxHandler;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M12 Offer & execution: templates, key facts, CBA schedule, PDF, delivery, ordered wet signing, acceptance, validity (FR-OFR-001..010). */
final class OfferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OfferFile::class);
        $this->app->scoped(OfferRegister::class, DatabaseOfferRegister::class);
    }

    public function boot(ConfigTypeCatalogue $types, ResourceResolver $resources, OutboxHandlerRegistry $outbox, EvidenceSources $evidence): void
    {
        // evidence pack sections (FR-AUD-010)
        $evidence->register('offers', OfferQueries::class, 'forApplication');
        $types->register(
            OfferFile::TEMPLATE_TYPE,
            'Offer letter / facility agreement template: HTML with placeholders, execution method, signing order, validity and disclosure method (FR-OFR-001/002/009, LOS-FR-314).',
            static function (array $content): void {
                OfferTemplate::validate($content);
            },
        );
        $app = $this->app;
        $scoped = static function (?string $applicationId, string $type, string $id) use ($app): ?ResourceAttributes {
            $a = $applicationId === null ? null : $app->make(ApplicationReader::class)->find($applicationId);

            return $a === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: $type, entityId: $id);
        };
        $resources->register('offer', static fn (string $id): ?ResourceAttributes => $scoped(preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Offer::query()->whereKey($id)->value('application_id') : null, 'offer', $id));
        $resources->register('offer_delivery', static function (string $id) use ($scoped): ?ResourceAttributes {
            $offerId = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? OfferDelivery::query()->whereKey($id)->value('offer_id') : null;

            return $scoped($offerId === null ? null : Offer::query()->whereKey($offerId)->value('application_id'), 'offer_delivery', $id);
        });
        $outbox->register(DeliverOfferHandler::EMAIL_TOPIC, SendOfferEmailOutboxHandler::class);
        if ($this->app->runningInConsole()) {
            $this->commands([ExpireOffersCommand::class]);
        }
    }
}
