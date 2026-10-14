<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Integration\Ports\Sms\SmsMessage;
use Fundly\Integration\Ports\Sms\SmsOperations;
use Fundly\Integration\Ports\Sms\SmsPort;
use Fundly\Integration\Ports\Sms\SmsReceipt;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;

/**
 * Sends through the `sms` integration port (HollaTags or the simulator).
 * The number is read at send time and never stored in full; the
 * communication id is the gateway's idempotency reference, so an outbox
 * retry after an unknown outcome cannot deliver twice. Temporary gateway
 * errors are `retry`; a definite refusal fails the message.
 */
final class DeliverSmsHandler implements CommandHandler
{
    public function __construct(private readonly PartyDirectory $parties, private readonly IntegrationGateway $gateway, private readonly Clock $clock) {}

    /** @return array{outcome: 'sent'|'retry'|'skipped'|'failed'} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeliverSms);
        $c = Communication::query()->find($command->communicationId) ?? throw new NotFound('Communication not found.');
        if ($c->status !== 'queued') {
            return ['outcome' => 'skipped'];
        }
        $phone = $c->recipient_party_id === null ? null : $this->parties->contactPhone($c->recipient_party_id);
        if ($phone === null) {
            $c->forceFill(['status' => 'failed', 'error' => 'No mobile number on file.'])->save();

            return ['outcome' => 'failed'];
        }
        $message = new SmsMessage(SmsOperations::international($phone, (string) config('fundly.sms.default_country_code', '234')), $c->body, $c->id);
        try {
            /** @var SmsReceipt $receipt */
            $receipt = $this->gateway->call(SmsPort::PORT, SmsPort::OP_SEND, SmsOperations::policy(), static function (object $adapter) use ($message): SmsReceipt {
                assert($adapter instanceof SmsPort);

                return $adapter->send($message);
            }, $message->forLog(), $c->id);
        } catch (IntegrationException $e) {
            $retry = $e->errorClass() === ErrorClass::Retryable;
            $c->forceFill(['attempts' => $c->attempts + 1, 'error' => mb_substr("{$e->canonicalCode}: {$e->getMessage()}", 0, 500), 'status' => $retry ? 'queued' : 'failed'])->save();

            return ['outcome' => $retry ? 'retry' : 'failed'];
        }
        $c->forceFill(['status' => 'sent', 'attempts' => $c->attempts + 1, 'error' => null, 'provider_ref' => $receipt->providerMessageId, 'sent_at' => $this->clock->now()])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'communication', entityId: $c->id, after: ['event' => $c->event, 'recipient' => $c->recipient_hint, 'engine' => $receipt->engine, 'provider_ref' => $receipt->providerMessageId]));

        return ['outcome' => 'sent'];
    }
}
