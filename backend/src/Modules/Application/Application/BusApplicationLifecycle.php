<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application;

use Fundly\Modules\Application\Application\Commands\AdvanceApplication;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;

final class BusApplicationLifecycle implements ApplicationLifecycle
{
    public function __construct(private readonly CommandBus $bus) {}

    public function advance(string $applicationId, CanonicalStatus $to, string $reasonCode, ?string $note, Principal $actor): void
    {
        $this->bus->dispatch(new AdvanceApplication($applicationId, $to, $reasonCode, $note, $actor->isSystem() ? null : 'application:view'), $actor);
    }
}
