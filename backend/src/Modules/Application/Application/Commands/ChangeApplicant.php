<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Add or remove a joint applicant, guarantor or co-signer (FR-APP-004). */
#[HandledBy(ChangeApplicantHandler::class)]
final readonly class ChangeApplicant implements Command, ValidatesInput
{
    public function __construct(
        public string $applicationId,
        public ?string $ifMatch,
        public string $operation,
        public string $partyId,
        public ?string $role,
    ) {}

    public function action(): string
    {
        return $this->operation === 'remove' ? 'application.applicant_removed' : 'application.applicant_added';
    }

    public function permission(): string
    {
        return 'application:originate';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['operation' => $this->operation, 'party_id' => $this->partyId, 'role' => $this->role];
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', 'in:add,remove'],
            'party_id' => ['required', 'uuid'],
            'role' => ['required_if:operation,add', 'nullable', 'in:joint,guarantor,co_signer'],
        ];
    }
}
