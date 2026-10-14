<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Infrastructure;

use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Modules\Notification\Application\Commands\DeliverEmail;
use Fundly\Modules\Notification\Application\Commands\NotifyHandler;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

final class DeliverEmailOutboxHandler implements OutboxHandler
{
    public function __construct(private readonly CommandBus $bus) {}

    public function topic(): string
    {
        return NotifyHandler::EMAIL_TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        /** @var array{outcome: string} $r */
        $r = $this->bus->dispatch(new DeliverEmail((string) $message->payload['communication_id']), Principal::system($message->tenantId, SystemIdentity::Outbox));
        if ($r['outcome'] === 'retry') {
            throw new RetryableError('NOTIFICATION.EMAIL.SEND_FAILED', 'The mail relay refused or failed; retrying.');
        }

        return $r;
    }
}
