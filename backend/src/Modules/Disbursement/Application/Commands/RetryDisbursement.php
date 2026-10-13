<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Run the booking saga again with the same instruction and keys (D-040b: no new checker; never a second posting). */
#[HandledBy(RetryDisbursementHandler::class)]
final readonly class RetryDisbursement implements Command
{
    public function __construct(public string $disbursementId) {}

    public function action(): string
    {
        return 'disbursement.retried';
    }

    public function permission(): string
    {
        return 'disbursement:retry';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('disbursement', $this->disbursementId);
    }
}
