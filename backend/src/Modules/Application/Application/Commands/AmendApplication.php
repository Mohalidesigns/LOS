<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Amend captured fields before approval, with field-level history and provenance (FR-APP-005, LOS-FR-301). */
#[HandledBy(AmendApplicationHandler::class)]
final readonly class AmendApplication implements Command, ValidatesInput
{
    /** @param array<string, mixed> $changes */
    public function __construct(
        public string $applicationId,
        public ?string $ifMatch,
        public array $changes,
        public string $source,
        public ?string $sourceRef,
    ) {}

    public function action(): string
    {
        return 'application.amended';
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
        return $this->changes + ['source' => $this->source, 'source_ref' => $this->sourceRef];
    }

    public function rules(): array
    {
        return [
            'requested_amount' => ['sometimes', 'nullable', 'regex:/^\d{1,16}(\.\d{1,4})?$/'],
            'tenor_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:500'],
            'repayment_frequency' => ['sometimes', 'nullable', 'in:weekly,monthly,quarterly,bullet'],
            'data' => ['sometimes', 'array'],
            'source' => ['required', 'in:cba,extraction,self_service,staff,partner'],
            'source_ref' => ['nullable', 'string', 'max:128'],
        ];
    }
}
