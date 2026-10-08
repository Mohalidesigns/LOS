<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Infrastructure;

use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Modules\Compliance\Application\Commands\ScreenApplication;
use Fundly\Modules\Compliance\Application\KycProgression;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

/** Runs intake screening off the request path; retried with backoff by the outbox if the provider fails. */
final class ScreenApplicationOutboxHandler implements OutboxHandler
{
    public function __construct(private readonly CommandBus $bus) {}

    public function topic(): string
    {
        return KycProgression::SCREEN_TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        $result = $this->bus->dispatch(
            new ScreenApplication((string) $message->payload['application_id'], (string) ($message->payload['trigger'] ?? 'intake')),
            Principal::system($message->tenantId, SystemIdentity::Outbox),
        );

        return is_array($result) ? $result : [];
    }
}
