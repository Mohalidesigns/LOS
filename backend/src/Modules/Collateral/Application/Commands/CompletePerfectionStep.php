<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Complete a perfection step with evidence: an uploaded file and/or a reference (registry number, stamp receipt). */
#[HandledBy(CompletePerfectionStepHandler::class)]
final readonly class CompletePerfectionStep implements Command, ValidatesInput
{
    public function __construct(public string $stepId, public ?string $note, public ?string $evidenceReference, public ?string $tempPath = null, public ?string $originalName = null) {}

    public function action(): string
    {
        return 'collateral.perfection_completed';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('perfection_step', $this->stepId);
    }

    public function data(): array
    {
        return ['note' => $this->note, 'evidence_reference' => $this->evidenceReference];
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:3', 'max:2000'], 'evidence_reference' => ['nullable', 'string', 'max:200']];
    }
}
