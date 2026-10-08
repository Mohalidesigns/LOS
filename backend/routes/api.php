<?php

declare(strict_types=1);

use Fundly\Integration\Runtime\Http\IntegrationController;
use Fundly\Modules\Access\Http\Controllers\AssignmentController;
use Fundly\Modules\Access\Http\Controllers\AuthController;
use Fundly\Modules\Access\Http\Controllers\ChangeRequestController;
use Fundly\Modules\Access\Http\Controllers\DelegationController;
use Fundly\Modules\Access\Http\Controllers\MeController;
use Fundly\Modules\Access\Http\Controllers\RoleController;
use Fundly\Modules\Access\Http\Controllers\SodController;
use Fundly\Modules\Access\Http\Controllers\UserController;
use Fundly\Modules\Application\Http\Controllers\ApplicationController;
use Fundly\Modules\Audit\Http\Controllers\AuditController;
use Fundly\Modules\Licensing\Http\Controllers\LicenceController;
use Fundly\Modules\Party\Http\Controllers\PartyController;
use Fundly\Modules\Platform\Http\Controllers\ConfigController;
use Fundly\Modules\Platform\Http\Controllers\LegalEntityController;
use Fundly\Modules\Platform\Http\Controllers\OrgUnitController;
use Fundly\Modules\Product\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
 * /api/v1 — the only HTTP surface (D-031). Every route declares an `authz:`
 * middleware (enforced by tests/Architecture/RoutesTest). POSTs that create or
 * cause an effect require Idempotency-Key (`idempotent`). High-risk actions
 * require a recent step-up (`stepup`). Read-only auditor routes and licence
 * recovery are `licence.exempt` (D-034 fail-safe).
 */

// ---- Authentication (no session yet) ---------------------------------------------------
Route::middleware(['authz:public', 'licence.exempt', 'throttle:login'])->prefix('auth')->group(function (): void {
    // SPA bootstrap: sets the XSRF-TOKEN cookie for Sanctum's CSRF protection (G-51).
    Route::get('csrf-cookie', [CsrfCookieController::class, 'show'])->name('auth.csrf-cookie');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('mfa/verify', [AuthController::class, 'verifyMfa'])->name('auth.mfa.verify');
});

// ---- Authenticated --------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'principal', 'session.policy', 'licence:core', 'throttle:api'])->group(function (): void {

    // Auth / self
    Route::middleware(['authz:authenticated', 'licence.exempt'])->group(function (): void {
        Route::post('auth/step-up', [AuthController::class, 'stepUp'])->middleware('throttle:login')->name('auth.step-up');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::get('me/effective-access', [MeController::class, 'effectiveAccess'])->name('me.effective-access');
    });

    // Platform: legal entities and org units (FR-TEN-001/003)
    Route::get('legal-entities', [LegalEntityController::class, 'index'])->middleware('authz:legal_entity:read')->name('legal-entities.index');
    Route::post('legal-entities', [LegalEntityController::class, 'store'])->middleware(['authz:legal_entity:manage', 'idempotent'])->name('legal-entities.store');
    Route::get('legal-entities/{id}', [LegalEntityController::class, 'show'])->middleware('authz:legal_entity:read')->name('legal-entities.show');
    Route::patch('legal-entities/{id}', [LegalEntityController::class, 'update'])->middleware('authz:legal_entity:manage')->name('legal-entities.update');

    Route::get('org-units', [OrgUnitController::class, 'index'])->middleware('authz:org_unit:read')->name('org-units.index');
    Route::post('org-units', [OrgUnitController::class, 'store'])->middleware(['authz:org_unit:manage', 'idempotent'])->name('org-units.store');
    Route::get('org-units/{id}', [OrgUnitController::class, 'show'])->middleware('authz:org_unit:read')->name('org-units.show');
    Route::patch('org-units/{id}', [OrgUnitController::class, 'update'])->middleware('authz:org_unit:manage')->name('org-units.update');

    // Platform: configuration artefacts (FR-TEN-009)
    Route::prefix('config-artifacts/{type}')->where(['type' => '[a-z0-9_.]+'])->group(function (): void {
        Route::get('', [ConfigController::class, 'index'])->middleware('authz:config:read')->name('config.index');
        Route::post('', [ConfigController::class, 'store'])->middleware(['authz:config:author', 'idempotent'])->name('config.store');
        Route::get('{artifact}', [ConfigController::class, 'show'])->middleware('authz:config:read')->name('config.show');
        Route::get('{artifact}/versions', [ConfigController::class, 'versions'])->middleware('authz:config:read')->name('config.versions');
        Route::post('{artifact}/versions', [ConfigController::class, 'storeVersion'])->middleware(['authz:config:author', 'idempotent'])->name('config.versions.store');
        Route::get('{artifact}/versions/{version}', [ConfigController::class, 'showVersion'])->middleware('authz:config:read')->name('config.versions.show');
        Route::patch('{artifact}/versions/{version}', [ConfigController::class, 'updateVersion'])->middleware('authz:config:author')->name('config.versions.update');
        Route::post('{artifact}/versions/{version}/actions/{action}', [ConfigController::class, 'transition'])
            ->middleware(['authz:config:read', 'stepup', 'idempotent'])->where('action', 'submit|approve|reject|activate|rollback')->name('config.versions.transition');
    });

    // Products: read-only catalogue for capture (FR-PRD-001); authoring is /config-artifacts/product
    Route::get('products', [ProductController::class, 'index'])->middleware('authz:application:view')->name('products.index');
    Route::get('products/{key}', [ProductController::class, 'show'])->middleware('authz:application:view')->where('key', '[a-z0-9][a-z0-9_.-]*')->name('products.show');

    // Parties (FR-CUS-001/006, FR-CHN-007)
    Route::get('parties', [PartyController::class, 'index'])->middleware('authz:application:view')->name('parties.index');
    Route::post('parties', [PartyController::class, 'store'])->middleware(['authz:party:manage', 'idempotent'])->name('parties.store');
    Route::post('parties/actions/match', [PartyController::class, 'match'])->middleware('authz:party:manage')->name('parties.match');
    Route::get('parties/{id}', [PartyController::class, 'show'])->middleware('authz:application:view')->name('parties.show');
    Route::get('parties/{id}/relationships', [PartyController::class, 'relationships'])->middleware('authz:application:view')->name('parties.relationships.index');
    Route::post('parties/{id}/relationships', [PartyController::class, 'addRelationship'])->middleware(['authz:party:manage', 'idempotent'])->name('parties.relationships.store');

    // Applications (FR-APP-*, LOS-FR-282/283/301)
    Route::get('applications', [ApplicationController::class, 'index'])->middleware('authz:application:view')->name('applications.index');
    Route::get('applications/stats', [ApplicationController::class, 'stats'])->middleware('authz:application:view')->name('applications.stats');
    Route::post('applications', [ApplicationController::class, 'store'])->middleware(['authz:application:originate', 'idempotent'])->name('applications.store');
    Route::get('applications/{id}', [ApplicationController::class, 'show'])->middleware('authz:application:view')->name('applications.show');
    Route::patch('applications/{id}', [ApplicationController::class, 'update'])->middleware('authz:application:originate')->name('applications.update');
    Route::post('applications/{id}/applicants', [ApplicationController::class, 'addApplicant'])->middleware(['authz:application:originate', 'idempotent'])->name('applications.applicants.store');
    Route::delete('applications/{id}/applicants/{partyId}', [ApplicationController::class, 'removeApplicant'])->middleware('authz:application:originate')->name('applications.applicants.destroy');
    Route::post('applications/{id}/actions/{action}', [ApplicationController::class, 'act'])->middleware(['authz:application:view', 'idempotent'])
        ->where('action', 'submit|withdraw|cancel|hold|resume|return|resubmit|recommend')->name('applications.act');
    Route::get('applications/{id}/timeline', [ApplicationController::class, 'timeline'])->middleware('authz:application:view')->name('applications.timeline');
    Route::get('applications/{id}/as-at', [ApplicationController::class, 'asAt'])->middleware('authz:application:view')->name('applications.as-at');

    // Access: users, roles, permissions, assignments (FR-SEC-001..008, 011, 014)
    Route::get('users', [UserController::class, 'index'])->middleware('authz:user:read')->name('users.index');
    Route::post('users', [UserController::class, 'store'])->middleware(['authz:user:manage', 'idempotent'])->name('users.store');
    Route::get('users/{id}', [UserController::class, 'show'])->middleware('authz:user:read')->name('users.show');
    Route::patch('users/{id}', [UserController::class, 'update'])->middleware(['authz:user:manage', 'stepup'])->name('users.update');
    Route::get('users/{id}/effective-access', [UserController::class, 'effectiveAccess'])->middleware('authz:user:read')->name('users.effective-access');
    Route::post('users/{id}/tokens', [UserController::class, 'issueToken'])->middleware(['authz:user:manage_tokens', 'stepup', 'idempotent'])->name('users.tokens.store');

    Route::get('roles', [RoleController::class, 'index'])->middleware('authz:role:read')->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->middleware(['authz:role:manage', 'idempotent'])->name('roles.store');
    Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('authz:role:read')->name('roles.show');
    Route::patch('roles/{id}', [RoleController::class, 'update'])->middleware('authz:role:manage')->name('roles.update');
    Route::post('roles/{id}/actions/clone', [RoleController::class, 'clone'])->middleware(['authz:role:manage', 'idempotent'])->name('roles.clone');
    Route::put('roles/{id}/permissions', [RoleController::class, 'setPermissions'])->middleware(['authz:role:manage', 'stepup'])->name('roles.permissions');
    Route::get('permissions', [RoleController::class, 'permissions'])->middleware('authz:permission:read')->name('permissions.index');

    Route::get('role-assignments', [AssignmentController::class, 'index'])->middleware('authz:role_assignment:read')->name('role-assignments.index');
    Route::post('role-assignments', [AssignmentController::class, 'store'])->middleware(['authz:role_assignment:request', 'stepup', 'idempotent'])->name('role-assignments.store');
    Route::delete('role-assignments/{id}', [AssignmentController::class, 'destroy'])->middleware(['authz:role_assignment:request', 'stepup'])->name('role-assignments.destroy');

    // Segregation of duties (FR-SEC-006)
    Route::get('sod-rules', [SodController::class, 'index'])->middleware('authz:sod_rule:read')->name('sod-rules.index');
    Route::post('sod-rules', [SodController::class, 'store'])->middleware(['authz:sod_rule:manage', 'stepup', 'idempotent'])->name('sod-rules.store');
    Route::delete('sod-rules/{id}', [SodController::class, 'destroy'])->middleware(['authz:sod_rule:manage', 'stepup'])->name('sod-rules.destroy');
    Route::get('sod-conflicts', [SodController::class, 'conflicts'])->middleware('authz:sod_rule:read')->name('sod-conflicts.index');

    // Delegations (FR-SEC-008)
    Route::get('delegations', [DelegationController::class, 'index'])->middleware('authz:authenticated')->name('delegations.index');
    Route::post('delegations', [DelegationController::class, 'store'])->middleware(['authz:delegation:create', 'stepup', 'idempotent'])->name('delegations.store');
    Route::delete('delegations/{id}', [DelegationController::class, 'destroy'])->middleware('authz:delegation:create')->name('delegations.destroy');

    // Maker-checker (FR-SEC-007, FR-TEN-009)
    Route::get('change-requests', [ChangeRequestController::class, 'index'])->middleware('authz:change_request:read')->name('change-requests.index');
    Route::get('change-requests/{id}', [ChangeRequestController::class, 'show'])->middleware('authz:change_request:read')->name('change-requests.show');
    Route::post('change-requests/{id}/actions/approve', [ChangeRequestController::class, 'approve'])->middleware(['authz:change_request:read', 'stepup', 'idempotent'])->name('change-requests.approve');
    Route::post('change-requests/{id}/actions/reject', [ChangeRequestController::class, 'reject'])->middleware(['authz:change_request:read', 'stepup', 'idempotent'])->name('change-requests.reject');
    Route::post('change-requests/{id}/actions/cancel', [ChangeRequestController::class, 'cancel'])->middleware(['authz:change_request:read', 'idempotent'])->name('change-requests.cancel');

    // Audit (FR-AUD-*). Read-only auditor access is licence fail-safe (D-034).
    Route::middleware('licence.exempt')->group(function (): void {
        Route::get('audit-events', [AuditController::class, 'index'])->middleware('authz:audit:read')->name('audit-events.index');
        Route::get('audit/verify', [AuditController::class, 'verify'])->middleware('authz:audit:verify')->name('audit.verify');
    });

    // Licensing (LOS-FR-316). Recovery endpoints stay available whatever the licence state.
    Route::middleware('licence.exempt')->group(function (): void {
        Route::get('licence', [LicenceController::class, 'show'])->middleware('authz:licence:read')->name('licence.show');
        Route::post('licence/import', [LicenceController::class, 'import'])->middleware(['authz:licence:import_request', 'stepup', 'idempotent'])->name('licence.import');
        Route::post('licence/activation-request', [LicenceController::class, 'activationRequest'])->middleware(['authz:licence:read', 'idempotent'])->name('licence.activation-request');
    });

    // Integration administration (read-only in P0)
    Route::get('adapter-bindings', [IntegrationController::class, 'bindings'])->middleware('authz:integration:read')->name('adapter-bindings.index');
    Route::get('adapter-bindings/{id}', [IntegrationController::class, 'binding'])->middleware('authz:integration:read')->name('adapter-bindings.show');
    Route::get('integration-calls', [IntegrationController::class, 'calls'])->middleware('authz:integration:read')->name('integration-calls.index');
});
