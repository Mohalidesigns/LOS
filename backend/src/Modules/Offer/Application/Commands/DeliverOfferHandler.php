<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Infrastructure\Models\OfferDelivery;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class DeliverOfferHandler implements CommandHandler
{
    public const EMAIL_TOPIC = 'offer.email_delivery';

    public function __construct(private readonly OfferFile $file, private readonly PartyDirectory $parties, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeliverOffer);
        $offer = $this->file->offer($command->offerId);
        if ($offer->status !== 'issued') {
            throw new DomainRuleViolation("Only an issued offer is delivered; this one is {$offer->status}.");
        }
        $app = $this->file->application($offer->application_id);
        $partyId = $command->partyId ?? (string) array_search('primary', $app->applicants, true);
        if (! isset($app->applicants[$partyId])) {
            throw ValidationFailed::with(['party_id' => 'The recipient must be a party on this application.']);
        }
        $hint = null;
        if ($command->channel === 'email') {
            $email = $this->parties->contactEmail($partyId) ?? throw ValidationFailed::with(['channel' => 'This party has no email address on file; print the offer instead.']);
            $hint = self::mask($email);
        }
        $delivery = new OfferDelivery;
        $delivery->forceFill([
            'offer_id' => $offer->id, 'channel' => $command->channel, 'party_id' => $partyId, 'recipient_hint' => $hint,
            'status' => $command->channel === 'email' ? 'queued' : 'recorded',
            'requested_by' => $context->principal->id, 'requested_at' => $this->file->now(),
            'completed_at' => $command->channel === 'print' ? $this->file->now() : null,
        ])->save();
        if ($command->channel === 'email') {
            $context->outbox(new OutboxIntent(
                topic: self::EMAIL_TOPIC,
                payload: ['delivery_id' => $delivery->id],
                aggregateType: 'offer',
                aggregateId: $offer->id,
                idempotencyKey: "{$context->principal->tenantId}:offer-email:{$delivery->id}",
                maxAttempts: 6,
            ));
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: ['delivery_id' => $delivery->id, 'channel' => $delivery->channel, 'party_id' => $partyId, 'recipient' => $hint]));

        return ['data' => $this->queries->present($offer)];
    }

    /** a****@example.ng: enough to confirm the address, never the whole of it in logs or the UI. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
    }
}
