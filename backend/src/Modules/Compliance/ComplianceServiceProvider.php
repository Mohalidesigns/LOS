<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance;

use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Audit\Contracts\EvidenceSources;
use Fundly\Modules\Compliance\Application\ComplianceQueries;
use Fundly\Modules\Compliance\Application\KycProgression;
use Fundly\Modules\Compliance\Application\Packs\PackFile;
use Fundly\Modules\Compliance\Application\StandardComplianceGate;
use Fundly\Modules\Compliance\Contracts\ComplianceGate;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Compliance\Contracts\Events\ScreeningAlertResolved;
use Fundly\Modules\Compliance\Contracts\RegulatoryRules;
use Fundly\Modules\Compliance\Domain\Packs\JurisdictionPack;
use Fundly\Modules\Compliance\Domain\PrudentialParameters;
use Fundly\Modules\Compliance\Infrastructure\ConfigBackedRegulatoryRules;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Modules\Compliance\Infrastructure\ScreenApplicationOutboxHandler;
use Fundly\Modules\Party\Contracts\Events\PartyKycChanged;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M18 Compliance (MVP slice): intake screening, four-eyes alert disposition, CDD progression gate, jurisdiction packs and the single-obligor limit. */
final class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ComplianceGate::class, StandardComplianceGate::class);
        $this->app->scoped(PackFile::class);
        $this->app->scoped(RegulatoryRules::class, ConfigBackedRegulatoryRules::class);
    }

    public function boot(ResourceResolver $resources, Dispatcher $events, OutboxHandlerRegistry $outbox, ConfigTypeCatalogue $types, EvidenceSources $evidence): void
    {
        // evidence pack sections (FR-AUD-010)
        $evidence->register('screening', ComplianceQueries::class, 'kycStatus');
        $types->register(PackFile::PACK_TYPE, 'Jurisdiction pack (key = jurisdiction.licence_category, e.g. ng.commercial_bank): the regulatory rule register with citations, effective dates and verification status (FR-CMP-001/003). Activates only when every rule is verified.', static function (array $content): void {
            JurisdictionPack::validate($content);
        });
        $types->registerActivationGate(PackFile::PACK_TYPE, static function (array $content): void {
            JurisdictionPack::assertActivatable($content, PackFile::allowUnverified());
        });
        $types->register(PackFile::PRUDENTIAL_TYPE, 'Prudential parameters of a legal entity (key = legal entity code): effective-dated shareholders\' funds unimpaired, the base for regulatory limits (LOS-FR-307).', static function (array $content): void {
            PrudentialParameters::validate($content);
        });
        $resources->register('screening_alert', static function (string $id): ?ResourceAttributes {
            $a = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ScreeningAlert::query()->find($id) : null;

            return $a === null ? null : new ResourceAttributes(orgUnitId: $a->org_unit_id, entityType: 'screening_alert', entityId: $a->id);
        });
        $outbox->register(KycProgression::SCREEN_TOPIC, ScreenApplicationOutboxHandler::class);

        $events->listen(ApplicationStatusChanged::class, [KycProgression::class, 'onStatusChanged']);
        $events->listen(ApplicationScreened::class, [KycProgression::class, 'onScreened']);
        $events->listen(ScreeningAlertResolved::class, [KycProgression::class, 'onAlertResolved']);
        $events->listen(PartyKycChanged::class, [KycProgression::class, 'onPartyKycChanged']);
    }
}
