<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(UpdateRoleHandler::class)]
final readonly class UpdateRole implements Command, ValidatesInput
{
    public function __construct(public string $roleId, public ?string $name, public ?string $description, public bool $descriptionProvided, public ?string $ifMatch) {}

    public function action(): string
    {
        return 'access.role.updated';
    }

    public function permission(): string
    {
        return Permission::RoleManage->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('role', $this->roleId);
    }

    public function data(): array
    {
        return ['name' => $this->name, 'description' => $this->description];
    }

    public function rules(): array
    {
        return ['name' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000']];
    }
}
