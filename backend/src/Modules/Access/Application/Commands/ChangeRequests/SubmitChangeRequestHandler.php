<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\ChangeRequests;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeRequestService;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

/** Shared handler for every "request X" command that becomes a change request. */
final class SubmitChangeRequestHandler implements CommandHandler
{
    public function __construct(private readonly ChangeRequestService $service) {}

    /** @return array<string, mixed> */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SubmitsChangeRequest);

        return $this->service->submit($command->changeActionType(), $command->changePayload(), $command->changeReason(), $context);
    }
}
