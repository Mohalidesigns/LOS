<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application\Commands;

use DateInterval;
use DateTimeImmutable;
use Fundly\Integration\Webhooks\WebhookPublisher;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Collateral\Contracts\SecuredItem;
use Fundly\Modules\Compliance\Contracts\RegulatoryRules;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Conditions\Contracts\OpenCondition;
use Fundly\Modules\Disbursement\Contracts\DisbursementRegister;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Document\Contracts\DocumentReference;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverPackage;
use Fundly\Modules\Handover\Infrastructure\Models\RetentionRecord;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Id\UuidV7;

/**
 * Builds the servicing handover package from the booked facility, the
 * accepted offer, the document references, the open conditions subsequent
 * and the insurance items; stores it as the exact JSON that was hashed;
 * starts the origination record's retention; and publishes
 * `facility.created` (identifiers and terms only, no personal data: the
 * receiver fetches the package through the API).
 */
final class CreateHandoverPackageHandler implements CommandHandler
{
    public const PACKAGE_VERSION = 2;

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly DisbursementRegister $disbursements,
        private readonly OfferRegister $offers,
        private readonly DocumentArchive $documents,
        private readonly ConditionsGate $conditions,
        private readonly PartyDirectory $parties,
        private readonly ProductCatalogue $products,
        private readonly WebhookPublisher $webhooks,
        private readonly Clock $clock,
        private readonly CollateralRegister $collateral,
        private readonly RegulatoryRules $rules,
    ) {}

    /** @return array{package_id: string, sha256: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateHandoverPackage);
        $app = $this->applications->find($command->applicationId) ?? throw new NotFound('Application not found.');
        if ($app->status !== CanonicalStatus::Booked) {
            throw new DomainRuleViolation("Handover follows booking; this application is {$app->status->value}.");
        }
        $facility = $this->disbursements->booked($app->id) ?? throw new DomainRuleViolation('No booked facility is on file.');
        $existing = HandoverPackage::query()->where('disbursement_id', $facility->disbursementId)->first();
        if ($existing !== null) {
            return ['package_id' => $existing->id, 'sha256' => $existing->sha256];
        }
        $offer = $this->offers->accepted($app->id) ?? throw new DomainRuleViolation('No accepted offer is on file.');
        $product = $this->products->version($app->productVersionId);
        $names = $this->parties->findMany(array_keys($app->applicants));
        $primary = (string) array_search('primary', $app->applicants, true);
        $last = $facility->schedule === [] ? null : $facility->schedule[count($facility->schedule) - 1];
        $maturity = (string) ($last['due_date'] ?? $facility->valueDate);
        $insuranceCodes = (array) config('fundly.handover.insurance_fee_codes', ['CRI']);
        $now = $this->clock->now();
        $retention = $this->retention($app->id, $app->legalEntityId, $now);

        $package = [
            'package_version' => self::PACKAGE_VERSION,
            'generated_at' => $now->format(DATE_ATOM),
            'application' => [
                'id' => $app->id, 'reference' => $app->reference, 'legal_entity_id' => $app->legalEntityId, 'org_unit_id' => $app->orgUnitId,
                'product' => ['key' => $product->key ?? null, 'name' => $product->name ?? null, 'version_no' => $product->versionNo ?? null], 'segment' => $app->segment,
            ],
            'borrower' => ['party_id' => $primary, 'name' => $names[$primary]->displayName ?? null, 'cba_customer_id' => $facility->cbaCustomerId],
            'parties' => array_values(array_map(static fn (string $id, string $role): array => ['party_id' => $id, 'role' => $role, 'name' => $names[$id]->displayName ?? null], array_keys($app->applicants), array_values($app->applicants))),
            'facility' => [
                'loan_account_no' => $facility->loanAccountNo, 'principal' => $facility->principal->toArray(), 'net_disbursed' => $facility->net->toArray(),
                'rate_percent' => $offer->ratePercent, 'tenor_months' => $offer->tenorMonths, 'repayment_frequency' => $offer->repaymentFrequency,
                'value_date' => $facility->valueDate, 'maturity_date' => $maturity, 'booked_at' => $facility->bookedAt->format(DATE_ATOM),
                'disbursement_posting_ref' => $facility->postingRef, 'fees_deducted' => $facility->fees, 'schedule' => $facility->schedule,
            ],
            'agreement' => ['offer_id' => $offer->offerId, 'agreement_id' => $offer->agreementId, 'sha256' => $offer->agreementSha256, 'template_version_id' => $offer->templateVersionId],
            'documents' => array_map(static fn (DocumentReference $d): array => [
                'document_id' => $d->documentId, 'version_id' => $d->versionId, 'version_no' => $d->versionNo, 'type' => $d->documentType, 'title' => $d->title,
                'filename' => $d->filename, 'mime_type' => $d->mimeType, 'sha256' => $d->sha256, 'stored_at' => $d->storedAt->format(DATE_ATOM),
                'href' => "/api/v1/documents/{$d->documentId}/versions/{$d->versionId}/content",
            ], $this->documents->references($app->id)),
            'conditions_subsequent' => array_map(static fn (OpenCondition $c): array => ['condition_id' => $c->id, 'description' => $c->description, 'mandatory' => $c->mandatory, 'owner_id' => $c->ownerId, 'due_date' => $c->dueDate], $this->conditions->openSubsequent($app->id)),
            'collateral' => array_map(static fn (SecuredItem $i): array => [
                'collateral_id' => $i->collateralId, 'type_code' => $i->typeCode, 'category' => $i->category, 'description' => $i->description,
                'owner' => ['party_id' => $i->ownerPartyId, 'name' => $i->ownerName], 'allocated' => $i->allocated->toArray(),
                'market_value' => $i->marketValue->toArray(), 'forced_sale_value' => $i->forcedSaleValue->toArray(), 'valuation_valid_until' => $i->valuationValidUntil,
                'open_perfection_steps' => $i->openSteps, 'insurance' => $i->insurance,
            ], $this->collateral->securedItems($app->id)),
            'insurance' => array_values(array_map(static fn (array $f): array => ['fee_code' => $f['code'], 'cover' => $f['name'], 'premium' => ['amount' => $f['amount'], 'currency' => $facility->principal->currency->code], 'cover_until' => $maturity],
                array_filter($facility->fees, static fn (array $f): bool => in_array($f['code'], $insuranceCodes, true)))),
            'servicing' => ['org_unit_id' => $app->orgUnitId, 'advice_document_id' => $facility->adviceDocumentId],
            'retention' => ['category' => $retention->category, 'trigger' => $retention->trigger, 'retain_until' => $retention->retain_until->toDateString()],
        ];
        $json = (string) json_encode($package, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sha = hash('sha256', $json);
        $p = new HandoverPackage;
        $p->forceFill([
            'application_id' => $app->id, 'disbursement_id' => $facility->disbursementId, 'package_json' => $json, 'sha256' => $sha,
            'servicing_org_unit_id' => $app->orgUnitId, 'created_by' => $context->principal->id, 'created_at' => $now,
        ]);
        $p->id = UuidV7::generate();
        $eventId = $this->webhooks->publish('facility.created', 'application', $app->id, [
            'application_id' => $app->id, 'reference' => $app->reference, 'legal_entity_id' => $app->legalEntityId,
            'loan_account_no' => $facility->loanAccountNo, 'cba_customer_id' => $facility->cbaCustomerId, 'product_key' => $product->key ?? null,
            'principal' => $facility->principal->toArray(), 'rate_percent' => $offer->ratePercent, 'tenor_months' => $offer->tenorMonths,
            'value_date' => $facility->valueDate, 'maturity_date' => $maturity, 'booked_at' => $facility->bookedAt->format(DATE_ATOM),
            'handover_package' => ['id' => $p->id, 'sha256' => $sha, 'href' => "/api/v1/handover-packages/{$p->id}"],
        ], $context);
        $p->webhook_event_id = $eventId;
        $p->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'handover_package', entityId: $p->id, after: [
            'application_id' => $app->id, 'sha256' => $sha, 'documents' => count($package['documents']), 'conditions_subsequent' => count($package['conditions_subsequent']),
            'insurance' => count($package['insurance']), 'webhook_event_id' => $eventId, 'retain_until' => $package['retention']['retain_until'],
        ]));

        return ['package_id' => $p->id, 'sha256' => $sha];
    }

    /** FR-HND-004: the origination record is kept for the configured period from booking, whatever happens downstream. */
    private function retention(string $applicationId, string $legalEntityId, DateTimeImmutable $now): RetentionRecord
    {
        $existing = RetentionRecord::query()->where('application_id', $applicationId)->where('category', 'origination_record')->first();
        if ($existing !== null) {
            return $existing;
        }
        // the jurisdiction pack's retention rule when one is active (FR-CMP-003), else the installation default
        $rule = $this->rules->rule($legalEntityId, 'origination_record_retention', $now);
        $years = max(1, $rule?->int('years', 0) ?: (int) config('fundly.retention.origination_years', 5));
        $source = $rule === null ? 'fundly.retention.origination_years' : "pack {$rule->packKey} v{$rule->packVersionNo}, rule origination_record_retention".($rule->verificationStatus === 'verified' ? '' : ' (unverified)');
        $r = new RetentionRecord;
        $r->forceFill([
            'application_id' => $applicationId, 'category' => 'origination_record', 'trigger' => 'fixed_from_booking',
            'starts_on' => $now->format('Y-m-d'), 'retain_until' => $now->add(new DateInterval("P{$years}Y"))->format('Y-m-d'),
            'legal_hold' => false, 'basis' => "{$years} years from booking ({$source}) until the LMS reports facility closure (P4)",
            'created_at' => $now,
        ])->save();

        return $r;
    }
}
