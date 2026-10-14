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
Schedule::command('approvals:expire')->hourly()->name('approvals.expire')->withoutOverlapping();                 // FR-APV-012
Schedule::command('offers:expire')->hourly()->name('offers.expire')->withoutOverlapping();                       // FR-OFR-007
Schedule::command('reconciliation:run')->dailyAt('02:00')->name('reconciliation.run')->withoutOverlapping();     // FR-DSB-008
Schedule::command('notifications:reminders')->dailyAt('08:00')->name('notifications.reminders')->withoutOverlapping(); // FR-OFR-007
Schedule::command('licence:check')->dailyAt('06:00')->name('licence.check');                                 // T-60/30/7 warnings
