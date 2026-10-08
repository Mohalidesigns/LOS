<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Console;

use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Modules\Licensing\Application\RequestLicenceImport;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\PrincipalKind;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Air-gapped licence import (TRD §2.5): verifies the file and raises a
 * maker-checker change request as the named maker. Nothing is installed until
 * a second administrator approves the request (API: change-requests approve).
 */
final class LicenceImportCommand extends Command
{
    protected $signature = 'licence:import {file : Licence file} {--maker= : Email of the administrator raising the request} {--tenant= : Tenant id (default: the installation tenant)} {--reason=}';

    protected $description = 'Raise a maker-checker request to import a signed licence file.';

    public function handle(CommandBus $bus, TenantContext $tenant, TenantDirectory $tenants, CurrentPrincipal $current): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }
        $signed = SignedLicence::fromFileContents((string) file_get_contents($path));
        $tenantOpt = $this->option('tenant');
        $tenantId = is_string($tenantOpt) && $tenantOpt !== '' ? $tenantOpt : ($tenants->activeTenantIds()[0] ?? null);
        if ($tenantId === null) {
            $this->error('No active tenant.');

            return self::FAILURE;
        }

        return $tenant->run($tenantId, function () use ($bus, $signed, $current, $tenantId): int {
            $email = (string) $this->option('maker');
            $makerId = DB::table('users')->whereRaw('lower(email) = ?', [mb_strtolower($email)])->where('kind', 'human')->where('status', 'active')->value('id');
            if (! is_string($makerId)) {
                $this->error('--maker must be the email of an active staff user holding licence:import_request.');

                return self::FAILURE;
            }
            $maker = new Principal($makerId, $tenantId, PrincipalKind::Human);
            $current->set($maker);
            $reason = $this->option('reason');
            /** @var array<string, mixed> $cr */
            $cr = $bus->dispatch(new RequestLicenceImport($signed->document, $signed->signature, is_string($reason) ? $reason : 'CLI import'), $maker);
            $this->info(sprintf('Change request %s raised (%s). A second administrator must approve it.', (string) $cr['id'], (string) $cr['status']));

            return self::SUCCESS;
        });
    }
}
