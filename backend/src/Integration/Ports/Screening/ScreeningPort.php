<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Screening;

use Fundly\Integration\Ports\Screening\Dto\ScreeningResult;
use Fundly\Integration\Ports\Screening\Dto\ScreeningSubject;

/**
 * Sanctions / PEP / adverse-media screening (FR-CUS-005, FR-CMP-011/013).
 * MVP binds the simulator; a live screening provider arrives in P4.
 */
interface ScreeningPort
{
    public const PORT = 'screening';

    public const OP_SCREEN = 'screenSubject';

    public function screen(ScreeningSubject $subject): ScreeningResult;
}
