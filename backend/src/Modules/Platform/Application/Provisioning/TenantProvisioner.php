<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Provisioning;

use Fundly\Modules\Access\Contracts\AccessProvisioning;
use Fundly\Modules\Platform\Infrastructure\Models\Tenant;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates a tenant with its role library, default SoD rules and bootstrap
 * administrators, in one transaction. Installation = tenant (D-033), so this
 * normally runs once per installation.
 */
final class TenantProvisioner
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly AccessProvisioning $access,
        private readonly AuditTrail $audit,
    ) {
    }

    /**
     * @param  list<array{email: string, name: string, password: string}>  $admins
     * @return array{tenant_id: string, admin_ids: list<string>}
     */
    public function provision(string $slug, string $name, array $admins, ?string $hostname = null): array
    {
        return $this->db->transaction(function () use ($slug, $name, $admins, $hostname): array {
            $t = new Tenant;
            $t->forceFill(['slug' => $slug, 'name' => $name, 'status' => 'active', 'hostname' => $hostname])->save();

            return $this->tenant->run($t->id, function () use ($t, $admins): array {
                $this->audit->record(new AuditEntry(action: 'platform.tenant.provisioned', entityType: 'tenant', entityId: $t->id, after: ['slug' => $t->slug, 'name' => $t->name]), Actor::system(SystemIdentity::Installer));
                $this->access->seedTenant();
                $ids = [];
                foreach ($admins as $a) {
                    $ids[] = $this->access->bootstrapAdministrator($a['email'], $a['name'], $a['password']);
                }

                return ['tenant_id' => $t->id, 'admin_ids' => $ids];
            });
        });
    }
}
