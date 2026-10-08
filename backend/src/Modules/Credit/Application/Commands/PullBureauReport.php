<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Credit bureau enquiry for an applicant (FR-CRD-003/004), gated on bureau consent (FR-CMP-020, P1-CUS-03). */
#[HandledBy(PullBureauReportHandler::class)]
final readonly class PullBureauReport implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public ?string $partyId) {}

    public function action(): string
    {
        return 'credit.bureau_report.pulled';
    }

    public function permission(): string
    {
        return 'bureau:pull';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['party_id' => $this->partyId];
    }

    public function rules(): array
    {
        return ['party_id' => ['nullable', 'uuid']];
    }
}
