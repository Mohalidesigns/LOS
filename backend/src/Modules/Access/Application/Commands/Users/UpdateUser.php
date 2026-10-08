<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Users;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(UpdateUserHandler::class)]
final readonly class UpdateUser implements Command, ValidatesInput
{
    /** @param array<string, mixed> $changes subset of name, status, home_org_unit_id */
    public function __construct(public string $userId, public array $changes, public ?string $ifMatch)
    {
    }

    public function action(): string
    {
        return 'access.user.updated';
    }

    public function permission(): string
    {
        return Permission::UserManage->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('user', $this->userId);
    }

    public function data(): array
    {
        return $this->changes;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', 'in:active,disabled'],
            'home_org_unit_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
