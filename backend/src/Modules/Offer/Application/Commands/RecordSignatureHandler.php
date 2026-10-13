<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Domain\SigningOrder;
use Fundly\Modules\Offer\Infrastructure\Models\OfferSignatory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;
use Illuminate\Support\Carbon;

final class RecordSignatureHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RecordSignature);
        $offer = $this->file->offer($command->offerId);
        $now = $this->file->now();
        if ($offer->status !== 'issued') {
            throw new DomainRuleViolation("Signatures are recorded on an issued offer; this one is {$offer->status}.");
        }
        if ($offer->valid_until->lessThan($now)) {
            throw new DomainRuleViolation('The offer has expired; it can no longer be signed (FR-OFR-007).');
        }
        $rows = OfferSignatory::query()->where('offer_id', $offer->id)->lockForUpdate()->get();
        $target = $rows->firstWhere('id', $command->signatoryId) ?? throw new NotFound('Signatory not found on this offer.');
        if ($target->signed_at !== null) {
            throw new DomainRuleViolation("{$target->display_name} has already signed.");
        }
        $next = SigningOrder::nextToSign(array_values($rows->map(static fn (OfferSignatory $s): array => ['id' => $s->id, 'position' => $s->position, 'signed' => $s->signed_at !== null])->values()->all()));
        if ($next !== $target->id) {
            $first = $rows->firstWhere('id', $next);
            throw new DomainRuleViolation("Signatures follow the template order: {$first?->display_name} (position {$first?->position}) signs first (FR-OFR-009).");
        }
        $signedAt = Carbon::parse((string) $command->signedAt);
        // signing times are captured to the second, so compare at that precision
        if ($signedAt->greaterThan($now) || $signedAt->lessThan($offer->issued_at->copy()->startOfSecond())) {
            throw ValidationFailed::with(['signed_at' => 'The signing date must be between the offer date and now.']);
        }
        $target->forceFill([
            'method' => $offer->execution_method, 'signed_at' => $signedAt, 'witness_name' => $command->witnessName, 'place' => $command->place,
            'recorded_by' => $context->principal->id, 'recorded_at' => $now,
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: [
            'signatory_id' => $target->id, 'position' => $target->position, 'role' => $target->role, 'party_id' => $target->party_id,
            'method' => $target->method, 'signed_at' => $signedAt->toIso8601String(), 'witness' => $command->witnessName,
        ]));

        return ['data' => $this->queries->present($offer)];
    }
}
