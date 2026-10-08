<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Domain\ApplicationAction;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Staff lifecycle actions: submit, recommend, hold/resume, return/resubmit, withdraw, cancel (LOS-FR-282/283, FR-APP-006). */
#[HandledBy(ActOnApplicationHandler::class)]
final readonly class ActOnApplication implements Command, ValidatesInput
{
    public function __construct(
        public string $applicationId,
        public ?string $ifMatch,
        public ApplicationAction $verb,
        public ?string $reasonCode,
        public ?string $reasonText,
        public ?string $returnTo,
    ) {}

    public function action(): string
    {
        return 'application.'.$this->verb->value;
    }

    public function permission(): string
    {
        return $this->verb->permission();
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['reason_code' => $this->reasonCode, 'reason_text' => $this->reasonText, 'return_to' => $this->returnTo];
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['nullable', 'string', 'regex:/^[A-Z0-9_]{2,64}$/'],
            'reason_text' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', 'max:32'],
        ];
    }
}
