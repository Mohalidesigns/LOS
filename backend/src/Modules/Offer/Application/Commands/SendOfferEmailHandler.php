<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Infrastructure\Models\OfferDelivery;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;

/**
 * Runs from the outbox. The mail goes through the installation's configured
 * mailer (bank SMTP relay); the full notification module (templates, SMS,
 * reminders) is P1-NTF-01. A failure leaves the delivery queued and the
 * outbox retries it.
 */
final class SendOfferEmailHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly PartyDirectory $parties, private readonly DocumentArchive $archive, private readonly Mailer $mailer) {}

    /** @return array{delivery_id: string, status: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SendOfferEmail);
        $delivery = OfferDelivery::query()->find($command->deliveryId) ?? throw new NotFound('Delivery not found.');
        if ($delivery->status !== 'queued') {
            return ['delivery_id' => $delivery->id, 'status' => $delivery->status];
        }
        $offer = $this->file->offer($delivery->offer_id);
        $app = $this->file->application($offer->application_id);
        $email = $delivery->party_id === null ? null : $this->parties->contactEmail($delivery->party_id);
        $pdf = $this->archive->content($offer->document_version_id);
        if ($email === null || $pdf === null || $offer->status !== 'issued') {
            $delivery->forceFill(['status' => 'failed', 'failure' => $offer->status !== 'issued' ? "Offer is {$offer->status}." : 'Recipient address or document is no longer available.', 'completed_at' => $this->file->now()])->save();
        } else {
            $this->mailer->raw(
                "Please find attached your loan offer {$app->reference} (offer #{$offer->sequence}). It is valid until {$offer->valid_until->toDateString()}. Read the key facts and repayment schedule carefully before you sign.",
                static function (Message $m) use ($email, $pdf, $app, $offer): void {
                    $m->to($email)->subject("Your loan offer {$app->reference}")->attachData($pdf, "{$app->reference}-offer-{$offer->sequence}.pdf", ['mime' => 'application/pdf']);
                },
            );
            $delivery->forceFill(['status' => 'sent', 'completed_at' => $this->file->now()])->save();
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: ['delivery_id' => $delivery->id, 'status' => $delivery->status, 'recipient' => $delivery->recipient_hint]));

        return ['delivery_id' => $delivery->id, 'status' => $delivery->status];
    }
}
