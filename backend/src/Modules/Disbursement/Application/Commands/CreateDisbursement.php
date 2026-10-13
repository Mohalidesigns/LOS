<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Make a disbursement instruction (maker side of FR-DSB-009); release goes to a checker as a change request. */
#[HandledBy(CreateDisbursementHandler::class)]
final readonly class CreateDisbursement implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public ?string $valueDate) {}

    public function action(): string
    {
        return 'disbursement.created';
    }

    public function permission(): string
    {
        return 'disbursement:make';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['value_date' => $this->valueDate];
    }

    public function rules(): array
    {
        return ['value_date' => ['nullable', 'date_format:Y-m-d']];
    }
}
