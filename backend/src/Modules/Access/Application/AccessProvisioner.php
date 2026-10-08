<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Contracts\AccessProvisioning;
use Fundly\Modules\Access\Domain\RoleLibrary;
use Fundly\Modules\Access\Domain\Scope;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Fundly\Modules\Access\Infrastructure\Models\SodRuleRecord;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Seeds the role library (LOS-FR-278) and bootstraps administrators. This is
 * the only path that grants access without maker-checker, so it is limited
 * to installation time and every write is audited as system:installer.
 */
final class AccessProvisioner implements AccessProvisioning
{
    public const ADMIN_ROLE_CODE = 'tenant_administrator';

    public function __construct(private readonly Hasher $hasher, private readonly AuditTrail $audit, private readonly Clock $clock) {}

    public function seedTenant(): void
    {
        $installer = Actor::system(SystemIdentity::Installer);
        foreach (RoleLibrary::templates() as $key => $tpl) {
            if (Role::query()->where('code', 'tpl_'.$key)->exists()) {
                continue;
            }
            $role = new Role;
            $role->forceFill([
                'code' => 'tpl_'.$key,
                'name' => $tpl['name'].' (template)',
                'description' => $tpl['description'],
                'is_template' => true,
                'template_key' => $key,
            ])->save();
            foreach ($tpl['permissions'] as $p) {
                (new RolePermission)->forceFill(['role_id' => $role->id, 'permission_code' => $p->value])->save();
            }
            $this->audit->record(new AuditEntry(action: 'access.role_template.seeded', entityType: 'role', entityId: $role->id, after: ['code' => $role->code, 'permissions' => array_map(static fn ($p) => $p->value, $tpl['permissions'])]), $installer);
        }

        foreach (RoleLibrary::defaultSodRules() as $r) {
            $rule = new SodRuleRecord;
            $rule->forceFill(['kind' => $r['kind'], 'left_ref' => $r['left'], 'right_ref' => $r['right'], 'description' => $r['description'], 'enabled' => true])->save();
            $this->audit->record(new AuditEntry(action: 'access.sod_rule.seeded', entityType: 'sod_rule', entityId: $rule->id, after: $r), $installer);
        }
    }

    public function bootstrapAdministrator(string $email, string $name, string $password): string
    {
        $installer = Actor::system(SystemIdentity::Installer);
        $role = Role::query()->where('code', self::ADMIN_ROLE_CODE)->first();
        if (! $role instanceof Role) {
            $template = Role::query()->where('template_key', 'tenant_administrator')->firstOrFail();
            $role = new Role;
            $role->forceFill(['code' => self::ADMIN_ROLE_CODE, 'name' => 'Tenant Administrator', 'description' => $template->description, 'is_template' => false, 'cloned_from_id' => $template->id])->save();
            foreach ($template->permissionCodes() as $code) {
                (new RolePermission)->forceFill(['role_id' => $role->id, 'permission_code' => $code])->save();
            }
        }

        $user = new User;
        $user->forceFill([
            'kind' => User::KIND_HUMAN,
            'email' => $email,
            'name' => $name,
            'password' => $this->hasher->make($password),
            'password_changed_at' => $this->clock->now(),
            'status' => 'active',
        ])->save();

        $assignment = new RoleAssignment;
        $assignment->forceFill([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope' => Scope::unrestricted()->toArray(),
            'valid_from' => $this->clock->now(),
            'granted_by' => SystemIdentity::Installer->value,
        ])->save();

        $this->audit->record(new AuditEntry(action: 'access.user.bootstrapped', entityType: 'user', entityId: $user->id, after: ['email' => $email, 'role' => self::ADMIN_ROLE_CODE, 'role_assignment_id' => $assignment->id]), $installer);

        return $user->id;
    }
}
