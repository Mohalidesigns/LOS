<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Application\Actions\SetRolePermissionsAction;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitChangeRequestHandler;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitsChangeRequest;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Changing what a role grants is a permission change: maker-checker (FR-SEC-007). */
#[HandledBy(SubmitChangeRequestHandler::class)]
final readonly class RequestRolePermissions implements SubmitsChangeRequest, ValidatesInput
{
    /** @param list<string> $permissions */
    public function __construct(public string $roleId, public array $permissions, public ?string $reason)
    {
    }

    public function action(): string
    {
        return 'access.role.permissions_change_requested';
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
        return ['permissions' => $this->permissions, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function changeActionType(): string
    {
        return SetRolePermissionsAction::TYPE;
    }

    public function changePayload(): array
    {
        $perms = array_values(array_unique($this->permissions));
        sort($perms);

        return ['role_id' => $this->roleId, 'permissions' => $perms];
    }

    public function changeReason(): ?string
    {
        return $this->reason;
    }
}
