<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tests\Support\OpenApiValidator;

function apiRoutes(): array
{
    return array_values(array_filter(Route::getRoutes()->getRoutes(), fn ($r) => ! str_starts_with($r->uri(), '_')));
}

it('gives every route an authorization middleware (no route bypasses the policy layer)', function () {
    $missing = [];
    foreach (apiRoutes() as $route) {
        $authz = array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'authz:'));
        if ($authz === []) {
            $missing[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }
    expect($missing)->toBe([]);
})->group('FR-SEC-014');

it('requires authentication on every non-public route, and only auth bootstrap and probes are public', function () {
    $public = [];
    foreach (apiRoutes() as $route) {
        $mw = $route->gatherMiddleware();
        if (in_array('authz:public', $mw, true)) {
            $public[] = $route->uri();
        } else {
            expect($mw)->toContain('auth:sanctum');
        }
    }
    expect($public)->toEqualCanonicalizing(['api/v1/auth/csrf-cookie', 'api/v1/auth/login', 'api/v1/auth/mfa/verify', 'health', 'ready']);
})->group('FR-SEC-014');

it('requires an Idempotency-Key on every effectful POST except interactive auth', function () {
    $exempt = ['api/v1/auth/login', 'api/v1/auth/mfa/verify', 'api/v1/auth/step-up', 'api/v1/auth/logout'];
    $missing = [];
    foreach (apiRoutes() as $route) {
        if (in_array('POST', $route->methods(), true) && ! in_array($route->uri(), $exempt, true) && ! in_array('idempotent', $route->gatherMiddleware(), true)) {
            $missing[] = $route->uri();
        }
    }
    expect($missing)->toBe([]);
})->group('FR-CBA-007');

it('documents exactly the implemented routes in the OpenAPI contract', function () {
    $routes = [];
    foreach (apiRoutes() as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $m) {
            $routes[] = strtolower($m).' /'.ltrim($route->uri(), '/');
        }
    }
    $spec = array_map(fn ($o) => $o['method'].' '.$o['path'], OpenApiValidator::instance()->operations());
    sort($routes);
    sort($spec);
    expect($routes)->toBe($spec);
    expect(OpenApiValidator::instance()->spec()['openapi'])->toBe('3.1.0');
})->group('FR-SEC-014');

it('declares a permission and security scheme for every documented operation', function () {
    foreach (OpenApiValidator::instance()->spec()['paths'] as $path => $item) {
        foreach ($item as $method => $op) {
            expect($op)->toHaveKeys(['x-permission', 'security', 'responses', 'operationId'])
                ->and($op['responses'])->toHaveKey('default');
        }
    }
});
