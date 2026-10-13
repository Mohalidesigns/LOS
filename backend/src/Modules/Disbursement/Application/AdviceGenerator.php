<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use Fundly\Integration\Ports\PdfRender\PdfRenderOperations;
use Fundly\Integration\Ports\PdfRender\PdfRenderPort;
use Fundly\Integration\Ports\PdfRender\RenderedPdf;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Disbursement\Domain\Display;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Document\Contracts\ArchivedDocument;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Offer\Contracts\AcceptedOffer;

/**
 * Disbursement advice with the final (CBA) schedule, filed as a document on
 * the application (FR-DSB-011). Delivery to the customer goes with the
 * notification module (P1-NTF-01).
 */
final class AdviceGenerator
{
    public function __construct(private readonly IntegrationGateway $gateway, private readonly DocumentArchive $archive) {}

    public function generate(Disbursement $d, ApplicationSummary $app, AcceptedOffer $offer): ArchivedDocument
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $m = static fn (string $amount): string => Display::money($amount, $d->currency);
        $rows = '';
        foreach ($d->fees as $f) {
            $rows .= '<tr><th>'.$e($f['name']).' (deducted)</th><td>'.$e($m($f['amount'])).'</td></tr>';
        }
        $schedule = '';
        foreach ((array) $d->cba_schedule as $i) {
            $schedule .= '<tr><td>'.$e((string) $i['number']).'</td><td>'.$e((string) $i['due_date']).'</td><td>'.$e($m((string) $i['principal'])).'</td><td>'.$e($m((string) $i['interest'])).'</td><td>'.$e($m((string) $i['total'])).'</td><td>'.$e($m((string) $i['closing_balance'])).'</td></tr>';
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Disbursement advice</title></head><body>'
            .'<h1>Disbursement advice</h1>'
            .'<p>Application '.$e($app->reference).' · loan account '.$e((string) $d->loan_account_no).' · value date '.$e($d->value_date->toDateString()).'</p>'
            .'<h2>Amounts</h2><table>'
            .'<tr><th>Loan amount</th><td>'.$e($m($d->principal_amount)).'</td></tr>'.$rows
            .'<tr><th>Amount paid to you</th><td>'.$e($m($d->net_amount)).'</td></tr>'
            .'<tr><th>Paid to</th><td>'.$e($d->destination['bank_code'].' / '.$d->destination['account'].' ('.($d->destination['account_name'] ?? '').')').'</td></tr>'
            .'<tr><th>Interest rate</th><td>'.$e($offer->ratePercent).'% per year over '.$e((string) $offer->tenorMonths).' months</td></tr>'
            .'<tr><th>Posting reference</th><td>'.$e((string) $d->posting_ref).'</td></tr></table>'
            .'<h2>Repayment schedule (as booked)</h2><table><tr><th>No.</th><th>Due date</th><th>Principal</th><th>Interest</th><th>Instalment</th><th>Balance</th></tr>'.$schedule.'</table>'
            .'</body></html>';

        /** @var RenderedPdf $pdf */
        $pdf = $this->gateway->call(PdfRenderPort::PORT, PdfRenderPort::OP_RENDER, PdfRenderOperations::policy(), static function (object $adapter) use ($html): RenderedPdf {
            assert($adapter instanceof PdfRenderPort);

            return $adapter->render($html);
        }, ['disbursement_id' => $d->id, 'html_bytes' => strlen($html)], null, $app->legalEntityId);

        return $this->archive->storeGenerated($app->id, 'DISBURSEMENT_ADVICE', 'Disbursement advice', "{$app->reference}-disbursement-advice.pdf", 'application/pdf', $pdf->bytes(), $d->released_by ?? $d->made_by);
    }
}
