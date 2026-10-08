<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Framework glue only (TRD §2.2). Module wiring lives in each module's provider. */
class AppServiceProvider extends ServiceProvider
{
    private const UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->runMigrationsAsSchemaOwner();

        Route::pattern('id', self::UUID);
        Route::pattern('artifact', self::UUID);
        Route::pattern('version', self::UUID);

        // Dedicated login limiter (TRD §8.4): per identity+IP and per IP. Lockout with backoff is on the account.
        RateLimiter::for('login', function (Request $request): array {
            $identity = mb_strtolower((string) $request->input('email', '')).'|'.$request->ip();

            return [
                Limit::perMinute((int) config('fundly.auth.rate_limit.per_identity_per_minute', 5))->by('login:id:'.$identity),
                Limit::perMinute((int) config('fundly.auth.rate_limit.per_ip_per_minute', 20))->by('login:ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(600)->by('api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Migrations must run as the schema owner, never as the runtime role.
     * When an operator runs `migrate*` without --database, switch the default
     * connection to the owner connection for the duration of the command.
     */
    private function runMigrationsAsSchemaOwner(): void
    {
        $previous = null;
        Event::listen(CommandStarting::class, function (CommandStarting $event) use (&$previous): void {
            if (! is_string($event->command) || ! str_starts_with($event->command, 'migrate')) {
                return;
            }
            if ($event->input->hasParameterOption('--database')) {
                return;
            }
            $previous = config('database.default');
            config(['database.default' => config('fundly.database.owner_connection')]);
            app('db')->setDefaultConnection((string) config('fundly.database.owner_connection'));
        });
        Event::listen(CommandFinished::class, function (CommandFinished $event) use (&$previous): void {
            if ($previous !== null && is_string($event->command) && str_starts_with($event->command, 'migrate')) {
                config(['database.default' => $previous]);
                app('db')->setDefaultConnection((string) $previous);
                $previous = null;
            }
        });
    }
}
