<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Audit\Contracts\EvidenceSources;
use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Application\ConditionQueries;
use Fundly\Modules\Conditions\Application\ConditionsProgression;
use Fundly\Modules\Conditions\Application\WaiveConditionAction;
use Fundly\Modules\Conditions\Console\OpenConditionsCommand;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Conditions\Infrastructure\DatabaseConditionsGate;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Modules\Offer\Contracts\Events\OfferAccepted;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M13 Conditions: CP/CS register, waivers through maker-checker, final pre-disbursement check (FR-CPR-001..005). */
final class ConditionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ConditionFile::class);
        $this->app->scoped(ConditionsGate::class, DatabaseConditionsGate::class);
    }

    public function boot(ResourceResolver $resources, Dispatcher $events, EvidenceSources $evidence): void
    {
        // evidence pack sections (FR-AUD-010)
        $evidence->register('conditions', ConditionQueries::class, 'forApplication');
        $this->app->make(ChangeActionRegistry::class)->register(WaiveConditionAction::TYPE, WaiveConditionAction::class);
        $app = $this->app;
        $resources->register('condition', static function (string $id) use ($app): ?ResourceAttributes {
            $c = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Condition::query()->find($id) : null;
            $a = $c === null ? null : $app->make(ApplicationReader::class)->find($c->application_id);

            return $a === null || $c === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'condition', entityId: $c->id);
        });
        $events->listen(OfferAccepted::class, [ConditionsProgression::class, 'onOfferAccepted']);
        if ($this->app->runningInConsole()) {
            $this->commands([OpenConditionsCommand::class]);
        }
    }
}
