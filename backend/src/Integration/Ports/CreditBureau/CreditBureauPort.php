<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CreditBureau;

/**
 * Credit bureau enquiry (FR-CRD-003/004). P1 binds one bureau simulator; the
 * Nigerian bureau adapters arrive in P4 behind the same port.
 */
interface CreditBureauPort
{
    public const PORT = 'credit_bureau';

    public const OP_FETCH = 'fetchReport';

    public function fetch(BureauSubject $subject): CreditProfile;
}
