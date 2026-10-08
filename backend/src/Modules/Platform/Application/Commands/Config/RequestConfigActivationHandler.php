<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class RequestConfigActivationHandler implements CommandHandler
{
    public function __construct(private readonly ChangeRequestGateway $changeRequests) {}

    /** @return array<string, mixed> */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RequestConfigActivation);

        return $this->changeRequests->submit(
            $command->actionType(),
            ['config_version_id' => $command->versionId, 'rollback' => $command->rollback],
            $command->reason,
            $context,
        );
    }
}
