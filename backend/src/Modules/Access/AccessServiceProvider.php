<?php

declare(strict_types=1);

namespace Fundly\Modules\Access;

use Fundly\Modules\Access\Application\AccessProvisioner;
use Fundly\Modules\Access\Application\Actions\GrantRoleAssignmentAction;
use Fundly\Modules\Access\Application\Actions\RevokeRoleAssignmentAction;
use Fundly\Modules\Access\Application\Actions\SetRolePermissionsAction;
use Fundly\Modules\Access\Application\Auth\AuthenticationService;
use Fundly\Modules\Access\Application\Authorizer;
use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Access\Application\ChangeRequests\ChangeRequestService;
use Fundly\Modules\Access\Application\PermissionCatalogue;
use Fundly\Modules\Access\Application\PrincipalActorProvider;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Modules\Access\Application\ScopeFilter;
use Fundly\Modules\Access\Contracts\AccessProvisioning;
use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Access\Infrastructure\Models\ChangeRequest;
use Fundly\Modules\Access\Infrastructure\Models\PersonalAccessToken;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(GrantRepository::class);
        $this->app->scoped(AuthorizationGate::class, Authorizer::class);
        $this->app->scoped(ListScopeFilter::class, ScopeFilter::class);
        $this->app->scoped(ActorProvider::class, PrincipalActorProvider::class);
        $this->app->singleton(ChangeActionRegistry::class);
        $this->app->scoped(ChangeRequestService::class);
        $this->app->scoped(ChangeRequestGateway::class, ChangeRequestService::class);
        $this->app->scoped(AccessProvisioning::class, AccessProvisioner::class);
        $this->app->scoped(AuthenticationService::class);
        $this->app->when(AuthenticationService::class)->needs(\Illuminate\Contracts\Auth\StatefulGuard::class)->give(fn (Application $app) => $app->make('auth')->guard('web'));
    }

    public function boot(ChangeActionRegistry $registry, ResourceResolver $resources): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $registry->register($this->app->make(GrantRoleAssignmentAction::class));
        $registry->register($this->app->make(RevokeRoleAssignmentAction::class));
        $registry->register($this->app->make(SetRolePermissionsAction::class));

        $resources->register('user', static function (string $id): ?ResourceAttributes {
            $u = User::query()->find($id);

            return $u === null ? null : AccessQueries::userAttributes($u);
        });
        $resources->register('role', static fn (string $id): ?ResourceAttributes => DB::table('roles')->where('id', $id)->exists() ? new ResourceAttributes(entityType: 'role', entityId: $id) : null);
        $resources->register('role_assignment', static function (string $id): ?ResourceAttributes {
            $a = RoleAssignment::query()->find($id);
            $u = $a === null ? null : User::query()->find($a->user_id);

            return $u === null ? null : new ResourceAttributes(legalEntityId: $u->home_legal_entity_id, orgUnitId: $u->home_org_unit_id, entityType: 'role_assignment', entityId: $id);
        });
        $resources->register('change_request', static fn (string $id): ?ResourceAttributes => ChangeRequest::query()->whereKey($id)->exists() ? new ResourceAttributes(entityType: 'change_request', entityId: $id) : null);
        $resources->register('sod_rule', static fn (string $id): ?ResourceAttributes => DB::table('sod_rules')->where('id', $id)->exists() ? new ResourceAttributes(entityType: 'sod_rule', entityId: $id) : null);
        $resources->register('delegation', static fn (string $id): ?ResourceAttributes => DB::table('delegations')->where('id', $id)->exists() ? new ResourceAttributes(entityType: 'delegation', entityId: $id) : null);

        // The code-defined permission catalogue is synced, as the schema owner, after every migration run.
        Event::listen(MigrationsEnded::class, static function (): void {
            PermissionCatalogue::sync(DB::connection((string) config('fundly.database.owner_connection')));
        });
    }
}
