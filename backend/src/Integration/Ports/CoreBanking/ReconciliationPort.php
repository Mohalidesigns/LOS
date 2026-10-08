<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Ports\CoreBanking\Dto\StatementLine;

/**
 * Reconciliation sub-port.
 */
interface ReconciliationPort
{
    /**
     * @return list<Posting>
     */
    public function getPostings(string $from, string $to): array;

    /**
     * @return list<StatementLine>
     */
    public function getAccountStatement(string $accountNo, string $from, string $to): array;
}
