<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure;

use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Modules\Disbursement\Application\Commands\RunBookingSaga;
use Fundly\Modules\Disbursement\Application\ReleaseDisbursementAction;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

/**
 * Runs the booking saga off the request path. The saga records its own
 * state and commits; a retryable outcome is handed back to the outbox as a
 * retryable error so it is re-delivered with backoff. Intervention and
 * compensation end here: they wait in the exception queue for a person.
 */
final class BookingSagaOutboxHandler implements OutboxHandler
{
    public function __construct(private readonly CommandBus $bus) {}

    public function topic(): string
    {
        return ReleaseDisbursementAction::SAGA_TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        /** @var array{outcome: string, status: string} $result */
        $result = $this->bus->dispatch(new RunBookingSaga((string) $message->payload['disbursement_id']), Principal::system($message->tenantId, SystemIdentity::Outbox));
        if ($result['outcome'] === 'retryable') {
            throw new RetryableError('LOS.DISBURSEMENT.RETRY', 'The booking saga will be retried: a CBA step failed transiently.');
        }

        return $result;
    }
}
