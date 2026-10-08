<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Assignments;

use Fundly\Modules\Access\Application\Actions\GrantRoleAssignmentAction;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitChangeRequestHandler;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\SubmitsChangeRequest;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(SubmitChangeRequestHandler::class)]
final readonly class RequestRoleAssignment implements SubmitsChangeRequest, ValidatesInput
{
    /** @param array<string, mixed> $scope */
    public function __construct(
        public string $userId,
        public string $roleId,
        public array $scope,
        public string $validFrom,
        public ?string $validTo,
        public ?string $reason,
    ) {}

    public function action(): string
    {
        return 'access.role_assignment.requested';
    }

    public function permission(): string
    {
        return Permission::RoleAssignmentRequest->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('user', $this->userId);
    }

    public function data(): array
    {
        return ['user_id' => $this->userId, 'role_id' => $this->roleId, 'scope' => $this->scope, 'valid_from' => $this->validFrom, 'valid_to' => $this->validTo];
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'role_id' => ['required', 'uuid'],
            'scope' => ['present', 'array'],
            'scope.legal_entity_ids' => ['sometimes', 'array'],
            'scope.legal_entity_ids.*' => ['uuid'],
            'scope.org_unit_id' => ['sometimes', 'nullable', 'uuid'],
            'scope.product_ids' => ['sometimes', 'array'],
            'scope.currencies' => ['sometimes', 'array'],
            'scope.currencies.*' => ['string', 'size:3'],
            'scope.max_amount' => ['sometimes', 'nullable', 'array'],
            'scope.max_amount.amount' => ['required_with:scope.max_amount', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'scope.max_amount.currency' => ['required_with:scope.max_amount', 'string', 'size:3'],
            'scope.segments' => ['sometimes', 'array'],
            'scope.portfolio_tags' => ['sometimes', 'array'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
        ];
    }

    public function changeActionType(): string
    {
        return GrantRoleAssignmentAction::TYPE;
    }

    public function changePayload(): array
    {
        return [
            'user_id' => $this->userId,
            'role_id' => $this->roleId,
            'scope' => $this->scope,
            'valid_from' => (new \DateTimeImmutable($this->validFrom))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'valid_to' => $this->validTo === null ? null : (new \DateTimeImmutable($this->validTo))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    public function changeReason(): ?string
    {
        return $this->reason;
    }
}
