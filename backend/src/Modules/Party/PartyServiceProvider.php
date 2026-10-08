<?php

declare(strict_types=1);

namespace Fundly\Modules\Party;

use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Party\Infrastructure\DatabaseConsentRegistry;
use Fundly\Modules\Party\Infrastructure\EloquentPartyDirectory;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M05 Party: individuals and companies, identities, relationships, dedupe. */
final class PartyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PartyDirectory::class, EloquentPartyDirectory::class);
        $this->app->scoped(ConsentRegistry::class, DatabaseConsentRegistry::class);
    }

    public function boot(ResourceResolver $resources): void
    {
        $resources->register('party', static function (string $id): ?ResourceAttributes {
            $p = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Party::query()->find($id) : null;

            return $p === null ? null : new ResourceAttributes(orgUnitId: $p->org_unit_id, entityType: 'party', entityId: $p->id);
        });
    }
}
