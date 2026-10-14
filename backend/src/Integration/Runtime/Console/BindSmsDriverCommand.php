<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Console;

use Fundly\Integration\Adapters\HollaTags\HollaTagsSms;
use Fundly\Integration\Ports\Sms\SmsPort;
use Fundly\Integration\Simulators\Sms\SmsSimulator;
use Illuminate\Console\Command;

/** Bind the `sms` port to the adapter SMS_DRIVER names (mock → simulator, hollatags → HollaTags). */
final class BindSmsDriverCommand extends Command
{
    protected $signature = 'integration:bind-sms';

    protected $description = 'Bind the sms port to the adapter named by SMS_DRIVER.';

    private const DRIVERS = ['mock' => SmsSimulator::KEY, 'simulator' => SmsSimulator::KEY, 'hollatags' => HollaTagsSms::KEY];

    public function handle(): int
    {
        $driver = strtolower((string) config('fundly.sms.driver', 'mock'));
        $adapter = self::DRIVERS[$driver] ?? null;
        if ($adapter === null) {
            $this->error("Unknown SMS_DRIVER '{$driver}'. Use one of: ".implode(', ', array_keys(self::DRIVERS)).'.');

            return self::FAILURE;
        }

        return $this->call('integration:bind', ['port' => SmsPort::PORT, 'adapter' => $adapter]);
    }
}
