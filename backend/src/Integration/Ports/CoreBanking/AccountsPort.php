<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\Account;
use Fundly\Integration\Ports\CoreBanking\Dto\AccountStatus;
use Fundly\Integration\Ports\CoreBanking\Dto\Balance;
use Fundly\Integration\Ports\CoreBanking\Dto\NameEnquiryResult;

/**
 * Accounts sub-port.
 */
interface AccountsPort
{
    /**
     * @return list<Account>
     */
    public function listAccounts(string $cbaCustomerId): array;

    public function getBalance(string $accountNo): Balance;

    public function verifyAccountStatus(string $accountNo): AccountStatus;

    public function nameEnquiry(string $bankCode, string $accountNo): NameEnquiryResult;
}
