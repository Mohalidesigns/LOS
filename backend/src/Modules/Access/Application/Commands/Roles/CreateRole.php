<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** A new role holds no permissions (deny by default, FR-SEC-003). */
#[HandledBy(CreateRoleHandler::class)]
final readonly class CreateRole implements Command, ValidatesInput
{
    public function __construct(public string $code, public string $name, public ?string $description)
    {
    }

    public function action(): string
    {
        return 'access.role.created';
    }

    public function permission(): string
    {
        return Permission::RoleManage->value;
    }

    public function resource(): ?ResourceRef
    {
        return null;
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
