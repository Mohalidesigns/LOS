<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Console;

use Fundly\Integration\Adapters\HollaTags\HollaTagsWhatsApp;
use Fundly\Integration\Ports\WhatsApp\WhatsAppPort;
use Fundly\Integration\Simulators\WhatsApp\WhatsAppSimulator;
use Illuminate\Console\Command;

/** Bind the `whatsapp` port to the adapter WHATSAPP_DRIVER names (mock → simulator, hollatags → HollaTags WhatsApp). */
final class BindWhatsAppDriverCommand extends Command
{
    protected $signature = 'integration:bind-whatsapp';

    protected $description = 'Bind the whatsapp port to the adapter named by WHATSAPP_DRIVER.';

    private const DRIVERS = ['mock' => WhatsAppSimulator::KEY, 'simulator' => WhatsAppSimulator::KEY, 'hollatags' => HollaTagsWhatsApp::KEY];

    public function handle(): int
    {
        $driver = strtolower((string) config('fundly.whatsapp.driver', 'mock'));
        $adapter = self::DRIVERS[$driver] ?? null;
        if ($adapter === null) {
            $this->error("Unknown WHATSAPP_DRIVER '{$driver}'. Use one of: ".implode(', ', array_keys(self::DRIVERS)).'.');

            return self::FAILURE;
        }

        return $this->call('integration:bind', ['port' => WhatsAppPort::PORT, 'adapter' => $adapter]);
    }
}
