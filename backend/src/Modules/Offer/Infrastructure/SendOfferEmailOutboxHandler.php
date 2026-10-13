<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure;

use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Modules\Offer\Application\Commands\DeliverOfferHandler;
use Fundly\Modules\Offer\Application\Commands\SendOfferEmail;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

/** Emails the offer letter off the request path; the outbox retries with backoff if the mail relay fails. */
final class SendOfferEmailOutboxHandler implements OutboxHandler
{
    public function __construct(private readonly CommandBus $bus) {}

    public function topic(): string
    {
        return DeliverOfferHandler::EMAIL_TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        $result = $this->bus->dispatch(new SendOfferEmail((string) $message->payload['delivery_id']), Principal::system($message->tenantId, SystemIdentity::Outbox));

        return is_array($result) ? $result : [];
    }
}
