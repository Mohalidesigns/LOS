<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Assignments;

use Fundly\Modules\Access\Application\Actions\RevokeRoleAssignmentAction;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitChangeRequestHandler;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitsChangeRequest;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(SubmitChangeRequestHandler::class)]
final readonly class RequestAssignmentRevocation implements SubmitsChangeRequest
{
    public function __construct(public string $assignmentId, public ?string $reason)
    {
    }

    public function action(): string
    {
        return 'access.role_assignment.revocation_requested';
    }

    public function permission(): string
    {
        return Permission::RoleAssignmentRequest->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('role_assignment', $this->assignmentId);
    }

    public function changeActionType(): string
    {
        return RevokeRoleAssignmentAction::TYPE;
    }

    public function changePayload(): array
    {
        return ['role_assignment_id' => $this->assignmentId];
    }

    public function changeReason(): ?string
    {
        return $this->reason;
    }
}
