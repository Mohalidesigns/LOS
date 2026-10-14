<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Integration\Ports\WhatsApp\WhatsAppMessage;
use Fundly\Integration\Ports\WhatsApp\WhatsAppOperations;
use Fundly\Integration\Ports\WhatsApp\WhatsAppPort;
use Fundly\Integration\Ports\WhatsApp\WhatsAppReceipt;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;

/**
 * Sends an approved template through the `whatsapp` port. Consent is
 * re-checked at send time (a withdrawal between queueing and sending wins).
 * Only a definite "not sent" (gateway busy) is retried; an unknown outcome
 * fails the message without resending, because the push API cannot
 * de-duplicate.
 */
final class DeliverWhatsAppHandler implements CommandHandler
{
    public function __construct(private readonly PartyDirectory $parties, private readonly ConsentRegistry $consents, private readonly IntegrationGateway $gateway, private readonly Clock $clock) {}

    /** @return array{outcome: 'sent'|'retry'|'skipped'|'failed'} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeliverWhatsApp);
        $c = Communication::query()->find($command->communicationId) ?? throw new NotFound('Communication not found.');
        if ($c->status !== 'queued') {
            return ['outcome' => 'skipped'];
        }
        $partyId = $c->recipient_party_id;
        if ($partyId === null || ! $this->consents->active($partyId, ConsentRegistry::WHATSAPP_MESSAGING)) {
            $c->forceFill(['status' => 'failed', 'error' => 'WhatsApp consent was withdrawn before sending.'])->save();

            return ['outcome' => 'failed'];
        }
        $phone = $this->parties->contactPhone($partyId);
        if ($phone === null) {
            $c->forceFill(['status' => 'failed', 'error' => 'No mobile number on file.'])->save();

            return ['outcome' => 'failed'];
        }
        $message = new WhatsAppMessage(WhatsAppOperations::international($phone, (string) config('fundly.sms.default_country_code', '234')), $c->subject, $c->body, $c->id);
        try {
            /** @var WhatsAppReceipt $receipt */
            $receipt = $this->gateway->call(WhatsAppPort::PORT, WhatsAppPort::OP_SEND_TEMPLATE, WhatsAppOperations::policy(), static function (object $adapter) use ($message): WhatsAppReceipt {
                assert($adapter instanceof WhatsAppPort);

                return $adapter->sendTemplate($message);
            }, $message->forLog(), $c->id);
        } catch (IntegrationException $e) {
            $retry = $e->errorClass() === ErrorClass::Retryable && ! $e->unknownOutcome;
            $note = $e->unknownOutcome ? ' The message may have been delivered; it was not resent.' : '';
            $c->forceFill(['attempts' => $c->attempts + 1, 'error' => mb_substr("{$e->canonicalCode}: {$e->getMessage()}{$note}", 0, 500), 'status' => $retry ? 'queued' : 'failed'])->save();

            return ['outcome' => $retry ? 'retry' : 'failed'];
        }
        $c->forceFill(['status' => 'sent', 'attempts' => $c->attempts + 1, 'error' => null, 'provider_ref' => $receipt->providerMessageId, 'sent_at' => $this->clock->now()])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'communication', entityId: $c->id, after: ['event' => $c->event, 'template' => $c->subject, 'recipient' => $c->recipient_hint, 'engine' => $receipt->engine]));

        return ['outcome' => 'sent'];
    }
}
