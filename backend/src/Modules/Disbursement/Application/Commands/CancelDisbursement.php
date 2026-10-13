<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Withdraw an instruction before it is released. */
#[HandledBy(CancelDisbursementHandler::class)]
final readonly class CancelDisbursement implements Command, ValidatesInput
{
    public function __construct(public string $disbursementId, public ?string $reason) {}

    public function action(): string
    {
        return 'disbursement.cancelled';
    }

    public function permission(): string
    {
        return 'disbursement:make';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('disbursement', $this->disbursementId);
    }

    public function data(): array
    {
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
