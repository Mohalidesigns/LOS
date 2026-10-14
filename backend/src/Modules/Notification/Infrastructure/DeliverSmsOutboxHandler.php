<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Infrastructure;

use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Modules\Notification\Application\Commands\DeliverSms;
use Fundly\Modules\Notification\Application\Commands\NotifyHandler;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

final class DeliverSmsOutboxHandler implements OutboxHandler
{
    public function __construct(private readonly CommandBus $bus) {}

    public function topic(): string
    {
        return NotifyHandler::SMS_TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        /** @var array{outcome: string} $r */
        $r = $this->bus->dispatch(new DeliverSms((string) $message->payload['communication_id']), Principal::system($message->tenantId, SystemIdentity::Outbox));
        if ($r['outcome'] === 'retry') {
            throw new RetryableError('NOTIFICATION.SMS.SEND_FAILED', 'The SMS gateway was unavailable; retrying.');
        }

        return $r;
    }
}
