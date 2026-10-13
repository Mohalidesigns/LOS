<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Brick\Math\BigDecimal;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationBreak;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationRun;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

/**
 * What the LOS believes it posted (disbursements and fee charges) against
 * what the CBA holds for the period, matched by posting reference. A posting
 * on one side only, or with a different amount, is a break (FR-DSB-008).
 */
final class RunReconciliationHandler implements CommandHandler
{
    public function __construct(private readonly DisbursementFile $file, private readonly IntegrationGateway $gateway, private readonly DisbursementQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RunReconciliation);
        $today = $this->file->now()->toDateString();
        $to = $command->to ?? $today;
        $from = $command->from ?? $this->file->now()->subDay()->toDateString();

        $los = [];
        foreach (Disbursement::query()->whereNotNull('posting_ref')->whereBetween('value_date', [$from, $to])->get() as $d) {
            $los[(string) $d->posting_ref] = ['amount' => $d->net_amount, 'currency' => $d->currency, 'account_no' => $d->loan_account_no, 'disbursement_id' => $d->id];
            foreach (array_values((array) $d->fee_posting_refs) as $i => $ref) {
                $fee = $d->fees[$i] ?? null;
                if ($fee !== null) {
                    $los[(string) $ref] = ['amount' => $fee['amount'], 'currency' => $d->currency, 'account_no' => $d->loan_account_no, 'disbursement_id' => $d->id];
                }
            }
        }
        /** @var list<Posting> $postings */
        $postings = $this->gateway->coreBanking('reconciliation.postings', static fn (CoreBankingPort $cba): array => $cba->reconciliation()->getPostings($from, $to), ['from' => $from, 'to' => $to]);

        $run = new ReconciliationRun;
        $run->forceFill(['from_date' => $from, 'to_date' => $to, 'los_postings' => count($los), 'cba_postings' => count($postings), 'matched' => 0, 'breaks' => 0, 'ran_by' => $context->principal->id, 'ran_at' => $this->file->now()]);
        $breaks = [];
        $matched = 0;
        $seen = [];
        foreach ($postings as $p) {
            $seen[$p->postingRef] = true;
            $mine = $los[$p->postingRef] ?? null;
            if ($mine === null) {
                $breaks[] = ['kind' => 'missing_in_los', 'reference' => $p->reference, 'posting_ref' => $p->postingRef, 'account_no' => $p->accountNo, 'los_amount' => null, 'cba_amount' => (string) $p->amount->amount, 'currency' => $p->amount->currency->code, 'disbursement_id' => null];
            } elseif (! BigDecimal::of($mine['amount'])->isEqualTo($p->amount->amount)) {
                $breaks[] = ['kind' => 'amount_mismatch', 'reference' => $p->reference, 'posting_ref' => $p->postingRef, 'account_no' => $p->accountNo, 'los_amount' => $mine['amount'], 'cba_amount' => (string) $p->amount->amount, 'currency' => $mine['currency'], 'disbursement_id' => $mine['disbursement_id']];
            } else {
                $matched++;
            }
        }
        foreach ($los as $ref => $mine) {
            if (! isset($seen[$ref])) {
                $breaks[] = ['kind' => 'missing_in_cba', 'reference' => (string) $ref, 'posting_ref' => (string) $ref, 'account_no' => $mine['account_no'], 'los_amount' => $mine['amount'], 'cba_amount' => null, 'currency' => $mine['currency'], 'disbursement_id' => $mine['disbursement_id']];
            }
        }
        $run->forceFill(['matched' => $matched, 'breaks' => count($breaks)])->save();
        foreach ($breaks as $b) {
            (new ReconciliationBreak)->forceFill($b + ['run_id' => $run->id, 'status' => 'open'])->save();
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'reconciliation_run', entityId: $run->id, after: ['from' => $from, 'to' => $to, 'los' => count($los), 'cba' => count($postings), 'matched' => $matched, 'breaks' => count($breaks)]));

        return ['data' => $this->queries->run($run)];
    }
}
