<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Delegations;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Delegate one of your own assignments to a colleague for a bounded period
 * (FR-SEC-008: mandatory expiry). The delegate acts "on behalf of" you; the
 * audit trail records both.
 */
#[HandledBy(CreateDelegationHandler::class)]
final readonly class CreateDelegation implements Command, ValidatesInput
{
    public function __construct(
        public string $roleAssignmentId,
        public string $delegateId,
        public string $validFrom,
        public ?string $validTo,
        public string $reason,
    ) {}

    public function action(): string
    {
        return 'access.delegation.created';
    }

    public function permission(): string
    {
        return Permission::DelegationCreate->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('role_assignment', $this->roleAssignmentId);
    }

    public function data(): array
    {
        return ['role_assignment_id' => $this->roleAssignmentId, 'delegate_id' => $this->delegateId, 'valid_from' => $this->validFrom, 'valid_to' => $this->validTo, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return [
            'role_assignment_id' => ['required', 'uuid'],
            'delegate_id' => ['required', 'uuid'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after:valid_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
