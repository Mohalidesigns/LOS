<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\ChangeRequests;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeRequestService;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class DecideChangeRequestHandler implements CommandHandler
{
    public function __construct(private readonly ChangeRequestService $service) {}

    /** @return array<string, mixed> */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DecideChangeRequest);

        return match ($command->decision) {
            DecideChangeRequest::APPROVE => $this->service->approve($command->changeRequestId, $command->reason, $context),
            DecideChangeRequest::REJECT => $this->service->reject($command->changeRequestId, (string) $command->reason, $context),
            default => $this->service->cancel($command->changeRequestId, $command->reason, $context),
        };
    }
}
