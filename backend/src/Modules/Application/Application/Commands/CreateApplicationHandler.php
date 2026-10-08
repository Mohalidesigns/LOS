<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use DateTimeZone;
use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\Support\EventPublisher;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Application\Infrastructure\ProvenanceRecorder;
use Fundly\Modules\Application\Infrastructure\ReferenceGenerator;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Id\UuidV7;

final class CreateApplicationHandler implements CommandHandler
{
    /** Save-and-resume window for drafts (FR-CHN-003); becomes tenant configuration in P1-CHN-01. */
    private const DRAFT_EXPIRY_DAYS = 30;

    public function __construct(
        private readonly ProductCatalogue $products,
        private readonly OrganisationDirectory $org,
        private readonly PartyDirectory $parties,
        private readonly ReferenceGenerator $references,
        private readonly ApplicationStore $store,
        private readonly ProvenanceRecorder $provenance,
        private readonly ApplicationQueries $queries,
        private readonly Clock $clock,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateApplication);
        $product = $this->products->activeByKey($command->productKey) ?? throw ValidationFailed::with(['product_key' => 'No active product with this key.']);
        $le = $this->org->legalEntity($command->legalEntityId) ?? throw ValidationFailed::with(['legal_entity_id' => 'Unknown legal entity.']);
        if (! $this->org->orgUnitBelongsTo($command->orgUnitId, $le->id)) {
            throw ValidationFailed::with(['org_unit_id' => 'The branch does not belong to this legal entity.']);
        }
        $party = $this->parties->find($command->primaryPartyId) ?? throw ValidationFailed::with(['primary_party_id' => 'Unknown party.']);
        if (! in_array($party->type, $product->applicantTypes, true)) {
            throw ValidationFailed::with(['primary_party_id' => "{$product->name} is not offered to {$party->type} applicants."]);
        }

        $now = $this->clock->now();
        $year = (int) $now->setTimezone(new DateTimeZone($le->timezone))->format('Y');
        $id = UuidV7::generate();
        $app = Application::create($id, [
            'reference' => $this->references->next($le->code, $year),
            'legal_entity_id' => $le->id,
            'org_unit_id' => $command->orgUnitId,
            'product_id' => $product->productId,
            'product_key' => $product->key,
            'product_version_id' => $product->versionId,
            'product_name' => $product->name,
            'segment' => $product->segment,
            'currency' => $product->currency,
            'channel' => $command->channel,
            'originator_id' => $context->principal->id,
            'primary_party_id' => $party->id,
            'primary_party_type' => $party->type,
            'primary_party_name' => $party->displayName,
            'requested_amount' => $command->requestedAmount,
            'tenor_months' => $command->tenorMonths,
            'purpose' => $command->purpose,
            'repayment_frequency' => $command->repaymentFrequency ?? (is_string($product->content['repayment_frequency'] ?? null) ? $product->content['repayment_frequency'] : null),
            'data' => $command->data,
            'expires_at' => $now->modify('+'.self::DRAFT_EXPIRY_DAYS.' days')->format(DATE_ATOM),
        ]);
        $written = $this->store->save($app, $context->principal, [
            'legal_entity_id' => $le->id,
            'org_unit_id' => $command->orgUnitId,
            'product_id' => $product->productId,
            'product_key' => $product->key,
            'product_version_id' => $product->versionId,
            'product_name' => $product->name,
            'segment' => $product->segment,
            'channel' => $command->channel,
            'originator_id' => $context->principal->id,
            'primary_party_id' => $party->id,
            'primary_applicant_name' => $party->displayName,
            'expires_at' => $now->modify('+'.self::DRAFT_EXPIRY_DAYS.' days'),
        ]);

        $captured = array_keys(array_filter([
            'requested_amount' => $command->requestedAmount, 'tenor_months' => $command->tenorMonths, 'purpose' => $command->purpose,
        ], static fn ($v): bool => $v !== null));
        foreach (array_keys($command->data) as $k) {
            $captured[] = 'data.'.$k;
        }
        $this->provenance->record($id, array_merge(['product_key', 'primary_party_id'], $captured), 1, $command->channel === 'api' ? 'partner' : 'staff', null, $context->principal->id);
        EventPublisher::publish($context, $app, $written);

        return $this->queries->viewForCommand($id);
    }
}
