<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Console;

use Fundly\Modules\Platform\Application\Provisioning\TenantProvisioner;
use Illuminate\Console\Command;

/**
 * Installation bootstrap: one tenant (D-033) with the role library and at
 * least two administrators, so maker-checker can operate from day one.
 * Passwords are read from the environment, never from the command line.
 */
final class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenant:provision {slug} {name} {--admin=* : name:email pairs; passwords from FUNDLY_BOOTSTRAP_PASSWORD_<n> (1-based)} {--hostname=}';

    protected $description = 'Create the installation tenant with its role library and bootstrap administrators.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $admins = [];
        foreach ((array) $this->option('admin') as $i => $spec) {
            [$name, $email] = array_pad(explode(':', (string) $spec, 2), 2, '');
            $password = getenv('FUNDLY_BOOTSTRAP_PASSWORD_'.($i + 1));
            if ($email === '' || ! is_string($password) || strlen($password) < 12) {
                $this->error('Admin #'.($i + 1).': use --admin="Name:email" and set FUNDLY_BOOTSTRAP_PASSWORD_'.($i + 1).' (12+ chars).');

                return self::FAILURE;
            }
            $admins[] = ['email' => $email, 'name' => $name, 'password' => $password];
        }
        if (count($admins) < 2) {
            $this->warn('Fewer than two administrators: maker-checker actions will need a second administrator before they can be approved.');
        }
        $hostname = $this->option('hostname');
        $result = $provisioner->provision((string) $this->argument('slug'), (string) $this->argument('name'), $admins, is_string($hostname) ? $hostname : null);
        $this->info("Tenant {$result['tenant_id']} provisioned with ".count($result['admin_ids']).' administrator(s).');

        return self::SUCCESS;
    }
}
