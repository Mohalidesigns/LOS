<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Handlers;

use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountRef;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Integration\Runtime\LookupBeforeRetry;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Shared\Money\Money;

/**
 * Outbox topic `cba.loan_account.create`: books a loan account exactly once
 * (lookup-before-retry on losFacilityId). The booking saga (P1) emits this.
 */
final class CreateLoanAccountHandler implements OutboxHandler
{
    public const TOPIC = 'cba.loan_account.create';

    public function __construct(private readonly IntegrationGateway $gateway) {}

    public function topic(): string
    {
        return self::TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        $p = $message->payload;
        $principal = is_array($p['principal'] ?? null) ? $p['principal'] : [];
        $request = new LoanAccountCreate(
            losFacilityId: (string) $p['los_facility_id'],
            cbaCustomerId: (string) $p['cba_customer_id'],
            productCode: (string) $p['product_code'],
            principal: Money::fromArray($principal),
            ratePercent: (string) $p['rate_percent'],
            tenorMonths: (int) $p['tenor_months'],
            frequency: (string) ($p['frequency'] ?? 'monthly'),
            moratoriumMonths: (int) ($p['moratorium_months'] ?? 0),
            startDate: (string) $p['start_date'],
            dayCount: (string) ($p['day_count'] ?? '30/360'),
            branchCode: (string) ($p['branch_code'] ?? '001'),
        );
        $key = $message->idempotencyKey ?? $message->id;
        $log = ['los_facility_id' => $request->losFacilityId, 'product_code' => $request->productCode, 'principal' => $request->principal->toArray()];

        /** @var LoanAccountRef $ref */
        $ref = LookupBeforeRetry::execute(
            send: fn (): LoanAccountRef => $this->gateway->coreBanking('loanAccount.create', static fn (CoreBankingPort $cba): LoanAccountRef => $cba->loanAccounts()->createLoanAccount($request, $key), $log, $key),
            lookup: fn (): ?LoanAccountRef => $this->gateway->coreBanking('loanAccount.findByLosReference', static fn (CoreBankingPort $cba): ?LoanAccountRef => $cba->loanAccounts()->findLoanAccountByLosReference($request->losFacilityId), ['los_facility_id' => $request->losFacilityId]),
            possiblySentBefore: $message->isRedelivery(),
        );

        return ['loan_account_no' => $ref->loanAccountNo, 'cba_reference' => $ref->cbaReference];
    }
}
