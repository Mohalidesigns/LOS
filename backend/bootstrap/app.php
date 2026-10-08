<?php

declare(strict_types=1);

use Fundly\Modules\Access\Http\Middleware\Authorize;
use Fundly\Modules\Access\Http\Middleware\BindPrincipal;
use Fundly\Modules\Access\Http\Middleware\EnforceSessionPolicy;
use Fundly\Modules\Access\Http\Middleware\RequireStepUp;
use Fundly\Modules\Access\Http\Middleware\ResolveTenantFromCredential;
use Fundly\Modules\Licensing\Http\Middleware\EnforceLicence;
use Fundly\Modules\Licensing\Http\Middleware\LicenceExempt;
use Fundly\Shared\Exceptions\ProblemException;
use Fundly\Shared\Http\Middleware\AssignCorrelationId;
use Fundly\Shared\Http\Middleware\RequireIdempotencyKey;
use Fundly\Shared\Http\ProblemRenderer;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        then: function (): void {
            Route::middleware([AssignCorrelationId::class])->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [AssignCorrelationId::class], append: [ResolveTenantFromCredential::class]);
        $middleware->alias([
            'authz' => Authorize::class,
            'principal' => BindPrincipal::class,
            'session.policy' => EnforceSessionPolicy::class,
            'stepup' => RequireStepUp::class,
            'licence' => EnforceLicence::class,
            'licence.exempt' => LicenceExempt::class,
            'idempotent' => RequireIdempotencyKey::class,
        ]);
        // Fixed pipeline order for route middleware.
        $middleware->priority([
            AssignCorrelationId::class,
            EncryptCookies::class,
            StartSession::class,
            ResolveTenantFromCredential::class,
            Authenticate::class,
            BindPrincipal::class,
            EnforceSessionPolicy::class,
            EnforceLicence::class,
            ThrottleRequests::class,
            Authorize::class,
            RequireStepUp::class,
            RequireIdempotencyKey::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => true);
        $exceptions->render(fn (Throwable $e, Request $request) => app(ProblemRenderer::class)->render($e, $request));
        $exceptions->dontReport([
            ProblemException::class,
        ]);
    })->create();
