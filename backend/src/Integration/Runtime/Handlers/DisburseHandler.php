<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Handlers;

use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\Destination;
use Fundly\Integration\Ports\CoreBanking\Dto\DisbursementRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Ports\CoreBanking\Dto\PostingResult;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Integration\Runtime\LookupBeforeRetry;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Shared\Money\Money;

/**
 * Outbox topic `cba.postings.disburse`. Never blind-retries a posting: on an
 * unknown outcome or a re-delivery it calls getPostingByReference(key) first
 * (register §2.3, FR-DSB-005).
 */
final class DisburseHandler implements OutboxHandler
{
    public const TOPIC = 'cba.postings.disburse';

    public function __construct(private readonly IntegrationGateway $gateway) {}

    public function topic(): string
    {
        return self::TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        $p = $message->payload;
        $key = $message->idempotencyKey ?? $message->id;
        $amount = is_array($p['amount'] ?? null) ? $p['amount'] : [];
        $dest = is_array($p['destination'] ?? null) ? $p['destination'] : [];
        $request = new DisbursementRequest(
            loanAccountNo: (string) $p['loan_account_no'],
            amount: Money::fromArray($amount),
            destination: new Destination((string) ($dest['type'] ?? 'internal'), (string) ($dest['account_no'] ?? ''), isset($dest['bank_code']) ? (string) $dest['bank_code'] : null),
            narration: (string) ($p['narration'] ?? 'Loan disbursement'),
            valueDate: (string) $p['value_date'],
            losReference: $key,
        );
        $log = ['loan_account_no' => $request->loanAccountNo, 'amount' => $request->amount->toArray(), 'destination' => ['type' => $request->destination->type, 'account_no' => $request->destination->accountNo]];

        $result = LookupBeforeRetry::execute(
            send: fn (): PostingResult => $this->gateway->coreBanking('postings.disburse', static fn (CoreBankingPort $cba): PostingResult => $cba->postings()->disburse($request, $key), $log, $key),
            lookup: function () use ($key): ?PostingResult {
                $posting = $this->gateway->coreBanking('postings.getByReference', static fn (CoreBankingPort $cba): ?Posting => $cba->postings()->getPostingByReference($key), ['reference' => $key]);

                return $posting === null ? null : new PostingResult($posting->postingRef, $posting->status);
            },
            possiblySentBefore: $message->isRedelivery(),
        );

        return ['posting_ref' => $result->postingRef, 'status' => $result->status];
    }
}
