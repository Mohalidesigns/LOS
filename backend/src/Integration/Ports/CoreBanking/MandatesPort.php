<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\StandingInstruction;

/**
 * Standing-instruction sub-port.
 */
interface MandatesPort
{
    /**
     * State-changing. Returns the SI reference.
     */
    public function createStandingInstruction(StandingInstruction $instruction, string $idempotencyKey): string;

    /**
     * State-changing.
     */
    public function cancelStandingInstruction(string $siReference, string $idempotencyKey): string;
}
