<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Override a policy outcome with reason, evidence and severity (FR-CRD-015); escalates approval authority. */
#[HandledBy(RaisePolicyExceptionHandler::class)]
final readonly class RaisePolicyException implements Command, ValidatesInput
{
    public function __construct(public string $decisionId, public string $reasonCode, public ?string $justification, public ?string $evidenceRef, public string $severity) {}

    public function action(): string
    {
        return 'credit.exception.raised';
    }

    public function permission(): string
    {
        return 'exception:raise';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('decision', $this->decisionId);
    }

    public function data(): array
    {
        return ['reason_code' => $this->reasonCode, 'justification' => $this->justification, 'evidence_ref' => $this->evidenceRef, 'severity' => $this->severity];
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,47}$/'],
            'justification' => ['required', 'string', 'min:20', 'max:4000'],
            'evidence_ref' => ['nullable', 'string', 'max:200'],
            'severity' => ['required', 'in:low,medium,high'],
        ];
    }
}
