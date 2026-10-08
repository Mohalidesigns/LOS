<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\TransitionGuard;
use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Application\RecommendGuard;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Modules\Credit\Domain\RuleSetDefinition;
use Fundly\Modules\Credit\Infrastructure\DatabaseCreditFile;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M08 Credit: rules engine use, bureau, decisions with replay, exceptions, credit memo. */
final class CreditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CreditFile::class, DatabaseCreditFile::class);
        $this->app->tag([RecommendGuard::class], TransitionGuard::TAG);
    }

    public function boot(ConfigTypeCatalogue $types, ResourceResolver $resources): void
    {
        $types->register(
            CreditFileLoader::RULE_SET_TYPE,
            'Credit rule set: formulas, knock-out / policy / grade / pricing decision tables and affordability (FR-CRD-001/002/008).',
            static function (array $content): void {
                RuleSetDefinition::validate($content);
            },
        );
        $app = $this->app;
        $resources->register('decision', static function (string $id) use ($app): ?ResourceAttributes {
            $d = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? DecisionSnapshot::query()->find($id) : null;
            if ($d === null) {
                return null;
            }
            $a = $app->make(ApplicationReader::class)->find($d->application_id);

            return new ResourceAttributes(legalEntityId: $a?->legalEntityId, orgUnitId: $a?->orgUnitId, productId: $a?->productId, segment: $a?->segment, entityType: 'decision', entityId: $d->id);
        });
    }
}
