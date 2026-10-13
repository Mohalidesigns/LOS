<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application;

use DateInterval;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\Instalment;
use Fundly\Integration\Ports\CoreBanking\Dto\ScheduleRequest;
use Fundly\Integration\Ports\PdfRender\PdfRenderOperations;
use Fundly\Integration\Ports\PdfRender\PdfRenderPort;
use Fundly\Integration\Ports\PdfRender\RenderedPdf;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Approval\Contracts\ApprovalView;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Offer\Domain\Amounts;
use Fundly\Modules\Offer\Domain\KeyFacts;
use Fundly\Modules\Offer\Domain\OfferTemplate;
use Fundly\Modules\Offer\Domain\SigningOrder;
use Fundly\Modules\Offer\Domain\TemplateRenderer;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Modules\Offer\Infrastructure\Models\OfferSignatory;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Money\Money;

/**
 * Builds one offer: terms from the standing approval, the schedule from the
 * core banking system (so the booked schedule matches what the customer
 * signs, FR-OFR-003), key facts, the rendered letter as PDF in the document
 * vault, and the ordered signatories.
 */
final class OfferBuilder
{
    private const ROLE_LABEL = ['primary' => 'Borrower', 'joint' => 'Joint borrower', 'guarantor' => 'Guarantor', 'co_signer' => 'Co-signer', 'bank' => 'Bank signatory'];

    public function __construct(
        private readonly OfferFile $file,
        private readonly ApprovalRegister $approvals,
        private readonly CreditFile $credit,
        private readonly IntegrationGateway $gateway,
        private readonly DocumentArchive $archive,
        private readonly PartyDirectory $parties,
        private readonly OrganisationDirectory $organisation,
    ) {}

    /** The approval an offer may be issued on: approved and in date, or a counter-offer. */
    public function standingApproval(ApplicationSummary $app): ApprovalView
    {
        $approval = $this->approvals->current($app->id) ?? throw new DomainRuleViolation('There is no approval decision to make an offer on.');
        if ($approval->status === 'approved' && ! $approval->isValidAt($this->file->now()->toDateTimeImmutable())) {
            throw new DomainRuleViolation('The approval has lapsed; the application needs re-approval before an offer (FR-APV-012).');
        }
        if (! in_array($approval->status, ['approved', 'counter_offered'], true)) {
            throw new DomainRuleViolation("The latest approval request is {$approval->status}; an offer needs an approval or a counter-offer.");
        }

        return $approval;
    }

    public function build(ApplicationSummary $app, string $actorId, ?Offer $replacing = null): Offer
    {
        $product = $this->file->product($app);
        $version = $this->file->activeTemplate($product);
        $template = OfferTemplate::of($version->content);
        $approval = $this->standingApproval($app);

        $counter = $approval->status === 'counter_offered';
        $principal = $counter ? $approval->counterAmount : $app->requestedAmount;
        $tenor = $counter ? $approval->counterTenorMonths : $app->tenorMonths;
        if ($principal === null || $tenor === null) {
            throw new DomainRuleViolation('The approved amount and tenor are incomplete.');
        }
        $rate = $this->credit->latestDecision($app->id)->ratePercent ?? (string) (((array) ($product->content['interest'] ?? []))['rate_percent'] ?? '0');
        $frequency = is_string($product->content['repayment_frequency'] ?? null) ? $product->content['repayment_frequency'] : 'monthly';
        $now = $this->file->now();

        /** @var list<Instalment> $instalments */
        $instalments = $this->gateway->coreBanking(
            'schedule.generate',
            static fn (CoreBankingPort $cba): array => $cba->schedules()->generateSchedule(new ScheduleRequest($product->key, $principal, $rate, $tenor, $frequency, 0, $now->toDateString(), '30/360')),
            ['application_id' => $app->id, 'principal' => $principal->toArray(), 'rate_percent' => $rate, 'tenor_months' => $tenor],
            null,
            $app->legalEntityId,
        );
        $schedule = array_map(static fn (Instalment $i): array => [
            'number' => $i->number, 'due_date' => $i->dueDate,
            'principal' => (string) $i->principal->amount, 'interest' => (string) $i->interest->amount,
            'total' => (string) $i->total->amount, 'closing_balance' => (string) $i->closingBalance->amount,
        ], $instalments);
        $currency = $principal->currency->code;
        $minor = $principal->currency->minorUnits();
        $fees = array_values(array_filter((array) ($product->content['fees'] ?? []), 'is_array'));
        $facts = KeyFacts::compute((string) $principal->amount, $minor, $schedule, $fees);
        $facts['cooling_off_days'] = $template->coolingOffDays();
        $instalment = $schedule[count($schedule) > 1 ? 1 : 0]['total'] ?? '0';

        $sequence = (int) Offer::query()->where('application_id', $app->id)->max('sequence') + 1;
        $validUntil = $now->copy()->add(new DateInterval('P'.($template->validityDays() ?? (is_int($product->content['offer_validity_days'] ?? null) ? $product->content['offer_validity_days'] : 30)).'D'));
        $names = $this->parties->findMany(array_keys($app->applicants));
        $parties = [];
        foreach ($app->applicants as $partyId => $role) {
            $parties[] = ['party_id' => (string) $partyId, 'role' => $role, 'name' => $names[$partyId]->displayName ?? 'Applicant'];
        }
        $bank = $this->organisation->legalEntity($app->legalEntityId)->name ?? 'the Bank';
        $signatories = SigningOrder::expected($template->signingOrder(), $parties, $bank);
        $primaryName = array_values(array_filter($parties, static fn (array $p): bool => $p['role'] === 'primary'))[0]['name'] ?? 'Applicant';

        $m = static fn (string $a): string => Amounts::money($a, $currency, $minor);
        $html = TemplateRenderer::render($template, [
            'applicant.name' => $primaryName, 'application.reference' => $app->reference, 'product.name' => $product->name, 'bank.name' => $bank,
            'offer.sequence' => (string) $sequence, 'offer.date' => $now->toDateString(), 'offer.valid_until' => $validUntil->toDateString(),
            'terms.amount' => $m((string) $principal->amount), 'terms.tenor_months' => (string) $tenor, 'terms.rate_percent' => Amounts::percent($rate),
            'terms.instalment' => $m($instalment), 'terms.repayment_frequency' => $frequency,
        ], [
            'key_facts' => $this->keyFactsBlock($facts, $m, $rate, $tenor, $instalment),
            'schedule' => TemplateRenderer::table('Repayment schedule', ['No.', 'Due date', 'Principal', 'Interest', 'Instalment', 'Balance'], array_map(
                static fn (array $r): array => [(string) $r['number'], $r['due_date'], $m($r['principal']), $m($r['interest']), $m($r['total']), $m($r['closing_balance'])],
                $schedule,
            ), [2, 3, 4, 5]),
            'conditions' => $this->conditionsBlock($approval),
            'signatures' => $this->signaturesBlock($signatories),
        ]);

        /** @var RenderedPdf $pdf */
        $pdf = $this->gateway->call(PdfRenderPort::PORT, PdfRenderPort::OP_RENDER, PdfRenderOperations::policy(), static function (object $adapter) use ($html): RenderedPdf {
            assert($adapter instanceof PdfRenderPort);

            return $adapter->render($html);
        }, ['application_id' => $app->id, 'template_version_id' => $version->id, 'html_bytes' => strlen($html)], null, $app->legalEntityId);
        $doc = $this->archive->storeGenerated($app->id, 'OFFER_LETTER', "{$template->title()} #{$sequence}", "{$app->reference}-offer-{$sequence}.pdf", 'application/pdf', $pdf->bytes(), $actorId);

        $offer = new Offer;
        $offer->forceFill([
            'application_id' => $app->id,
            'sequence' => $sequence,
            'status' => 'issued',
            'template_key' => $version->key,
            'template_version_id' => $version->id,
            'template_version_no' => $version->versionNo,
            'execution_method' => $template->executionMethod(),
            'approval_request_id' => $approval->requestId,
            'terms' => ['amount' => $principal->toArray(), 'tenor_months' => $tenor, 'rate_percent' => $rate, 'repayment_frequency' => $frequency, 'instalment' => Money::of($instalment, $currency)->toArray(), 'source' => $counter ? 'counter_offer' : 'approval'],
            'key_facts' => $facts,
            'schedule' => $schedule,
            'schedule_source' => 'core_banking:schedule.generate',
            'document_id' => $doc->documentId,
            'document_version_id' => $doc->versionId,
            'document_sha256' => $doc->sha256,
            'issued_by' => $actorId,
            'issued_at' => $now,
            'valid_until' => $validUntil,
            'reissue_of' => $replacing?->id,
            'reissue_count' => $replacing === null ? 0 : $replacing->reissue_count + 1,
        ])->save();
        foreach ($signatories as $s) {
            (new OfferSignatory)->forceFill(['offer_id' => $offer->id, 'position' => $s['position'], 'role' => $s['role'], 'party_id' => $s['party_id'], 'display_name' => $s['name']])->save();
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $f
     * @param  \Closure(string): string  $m
     */
    private function keyFactsBlock(array $f, \Closure $m, string $rate, int $tenor, string $instalment): string
    {
        $rows = [
            ['Amount of credit', $m((string) $f['principal'])],
            ['Duration', "{$tenor} months ({$f['instalment_count']} instalments)"],
            ['Interest rate (nominal, per year)', Amounts::percent($rate)],
            ['Regular instalment', $m($instalment)],
            ['Total interest', $m((string) $f['total_interest'])],
        ];
        $contingent = [];
        foreach ((array) $f['fees'] as $fee) {
            if ($fee['costed']) {
                $rows[] = ["{$fee['name']} ({$fee['type']})", $m((string) $fee['amount'])];
            } else {
                $contingent[] = $fee['name'];
            }
        }
        array_push(
            $rows,
            ['Total fees', $m((string) $f['total_fees'])],
            ['Total cost of credit (interest + fees)', $m((string) $f['total_cost_of_credit'])],
            ['Total amount repayable', $m((string) $f['total_repayable'])],
            ['Amount you receive (after upfront fees)', $m((string) $f['net_disbursed'])],
            ['Annual percentage rate (APR)', Amounts::percent((string) $f['apr_percent'])],
            ['Effective annual rate', Amounts::percent((string) $f['effective_annual_rate_percent'])],
        );
        if ($contingent !== []) {
            $rows[] = ['Charged only if they arise', implode(', ', $contingent)];
        }
        if ((int) $f['cooling_off_days'] > 0) {
            $rows[] = ['Cooling-off period', "You may withdraw within {$f['cooling_off_days']} days of signing without penalty."];
        }

        return TemplateRenderer::keyValueTable('Key facts and cost of credit', $rows);
    }

    private function conditionsBlock(ApprovalView $approval): string
    {
        if ($approval->conditions === []) {
            return '';
        }
        $html = '<h2>Conditions</h2><ul>';
        foreach ($approval->conditions as $c) {
            $html .= '<li>'.TemplateRenderer::e(($c->kind === 'precedent' ? 'Before disbursement: ' : 'After drawdown: ').$c->description).'</li>';
        }

        return $html.'</ul>';
    }

    /** @param list<array{position: int, role: string, party_id: ?string, name: string}> $signatories */
    private function signaturesBlock(array $signatories): string
    {
        $html = '<h2>Acceptance</h2><p>Each party signs in the order below.</p>';
        foreach ($signatories as $s) {
            $html .= '<div class="sig">'.TemplateRenderer::e("{$s['position']}. {$s['name']}, ".self::ROLE_LABEL[$s['role']]).'<br>Signature: ______________________  Date: ____________</div>';
        }

        return $html;
    }
}
