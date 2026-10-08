<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->runMigrationsAsSchemaOwner();
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
