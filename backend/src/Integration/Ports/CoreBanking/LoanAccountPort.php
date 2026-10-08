<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccount;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountRef;

/**
 * Loan account sub-port.
 */
interface LoanAccountPort
{
    /**
     * State-changing. Lookup-before-retry on losFacilityId.
     */
    public function createLoanAccount(LoanAccountCreate $request, string $idempotencyKey): LoanAccountRef;

    public function getLoanAccount(string $loanAccountNo): LoanAccount;

    /**
     * Lookup used before re-sending createLoanAccount.
     */
    public function findLoanAccountByLosReference(string $losFacilityId): ?LoanAccountRef;

    /**
     * State-changing.
     *
     * @param  array<string, string>  $terms
     */
    public function amendTerms(string $loanAccountNo, array $terms, string $idempotencyKey): string;

    /**
     * State-changing.
     */
    public function closeAccount(string $loanAccountNo, string $idempotencyKey): string;
}
