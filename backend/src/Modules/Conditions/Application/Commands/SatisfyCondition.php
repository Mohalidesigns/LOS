<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Sign off a condition with evidence: an uploaded document or a reference (FR-CPR-001). */
#[HandledBy(SatisfyConditionHandler::class)]
final readonly class SatisfyCondition implements Command, ValidatesInput
{
    public function __construct(public string $conditionId, public ?string $note, public ?string $evidenceReference, public ?string $tempPath, public ?string $originalName) {}

    public function action(): string
    {
        return 'condition.satisfied';
    }

    public function permission(): string
    {
        return 'condition:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('condition', $this->conditionId);
    }

    public function data(): array
    {
        return ['note' => $this->note, 'evidence_reference' => $this->evidenceReference];
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:5', 'max:2000'], 'evidence_reference' => ['nullable', 'string', 'max:200']];
    }
}
