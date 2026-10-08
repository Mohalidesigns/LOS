<?php

declare(strict_types=1);

use Fundly\Shared\Http\HealthController;
use Illuminate\Support\Facades\Route;

// Liveness/readiness probes: unauthenticated, no session, no tenant data.
Route::middleware('authz:public')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health'])->name('health');
    Route::get('/ready', [HealthController::class, 'ready'])->name('ready');
});
