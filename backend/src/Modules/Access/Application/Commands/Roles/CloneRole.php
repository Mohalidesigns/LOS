<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Clone a library template or an existing role into a new, assignable role
 * (FR-SEC-002, LOS-FR-278). The clone is not held by anyone until an
 * assignment is approved through maker-checker.
 */
#[HandledBy(CloneRoleHandler::class)]
final readonly class CloneRole implements Command, ValidatesInput
{
    public function __construct(public string $sourceRoleId, public string $code, public string $name, public ?string $description)
    {
    }

    public function action(): string
    {
        return 'access.role.cloned';
    }

    public function permission(): string
    {
        return Permission::RoleManage->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('role', $this->sourceRoleId);
    }

    public function data(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'description' => $this->description];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,63}$/'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
