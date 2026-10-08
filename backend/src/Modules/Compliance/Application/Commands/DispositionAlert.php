<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Step 1 (propose: screening:review) or step 2 (confirm: screening:clear) of
 * the four-eyes disposition of a screening alert (FR-CMP-014/017).
 */
#[HandledBy(DispositionAlertHandler::class)]
final readonly class DispositionAlert implements Command, ValidatesInput
{
    public function __construct(
        public string $alertId,
        public string $step,
        public ?string $decision,
        public ?string $reason,
        public ?string $evidenceRef,
    ) {}

    public function action(): string
    {
        return $this->step === 'confirm' ? 'compliance.screening_alert.confirmed' : 'compliance.screening_alert.proposed';
    }

    public function permission(): string
    {
        return $this->step === 'confirm' ? 'screening:clear' : 'screening:review';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('screening_alert', $this->alertId);
    }

    public function data(): array
    {
        return ['step' => $this->step, 'decision' => $this->decision, 'reason' => $this->reason, 'evidence_ref' => $this->evidenceRef];
    }

    public function rules(): array
    {
        return [
            'step' => ['required', 'in:propose,confirm'],
            'decision' => ['required_if:step,propose', 'nullable', 'in:clear,true_match'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_ref' => ['nullable', 'string', 'max:200'],
        ];
    }
}
