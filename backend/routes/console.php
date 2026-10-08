<?php

declare(strict_types=1);

use Fundly\Integration\Runtime\Outbox\DispatchOutboxJob;
use Illuminate\Support\Facades\Schedule;

/*
 * Scheduler (runs in the `scheduler` container: php artisan schedule:work).
 */
Schedule::job(new DispatchOutboxJob)->everyMinute()->name('outbox.dispatch')->withoutOverlapping();
Schedule::command('audit:checkpoint')->everyFiveMinutes()->name('audit.checkpoint')->withoutOverlapping();   // TRD §5.4
Schedule::command('audit:verify')->dailyAt('01:30')->name('audit.verify')->withoutOverlapping();           // nightly; non-zero exit alerts
Schedule::command('licence:check')->dailyAt('06:00')->name('licence.check');                                 // T-60/30/7 warnings
