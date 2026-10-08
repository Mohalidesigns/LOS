<?php

declare(strict_types=1);

namespace Database\Seeders;

use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Platform\Application\Provisioning\TenantProvisioner;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Development/UAT demo data: one tenant with two administrators and the CBA
 * simulator bound. Refuses to run in a production installation.
 * Passwords: FUNDLY_DEMO_PASSWORD (12+ chars), never committed.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(TenantProvisioner $provisioner, TenantContext $tenant): void
    {
        if (config('fundly.installation.environment') === 'production') {
            throw new RuntimeException('Demo seeding is disabled in production installations.');
        }
        $password = (string) env('FUNDLY_DEMO_PASSWORD', '');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set FUNDLY_DEMO_PASSWORD (12+ characters) to seed demo administrators.');
        }
        $result = $provisioner->provision('demo-bank', 'Demo Bank Plc', [
            ['email' => 'admin1@demo-bank.test', 'name' => 'Demo Admin One', 'password' => $password],
            ['email' => 'admin2@demo-bank.test', 'name' => 'Demo Admin Two', 'password' => $password],
        ], 'localhost');
        $tenant->run($result['tenant_id'], static function (): void {
            AdapterBinding::query()->create([
                'port' => 'core_banking', 'adapter_key' => 'cba-simulator', 'adapter_version' => '1.0.0',
                'config' => [], 'processing_location' => 'on_prem:simulator', 'status' => 'active',
            ]);
        });
    }
}
