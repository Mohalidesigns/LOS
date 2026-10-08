<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Application\Support\EventPublisher;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class AdvanceApplicationHandler implements CommandHandler
{
    public function __construct(private readonly ApplicationStore $store) {}

    public function handle(Command $command, CommandContext $context): null
    {
        assert($command instanceof AdvanceApplication);
        $app = $this->store->load($command->applicationId);
        $app->advance($command->to, $command->reasonCode, $command->note);
        EventPublisher::publish($context, $app, $this->store->save($app, $context->principal));

        return null;
    }
}
