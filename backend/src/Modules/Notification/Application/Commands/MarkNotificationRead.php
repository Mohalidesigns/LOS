<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Mark one of my in-app notifications read. */
#[HandledBy(MarkNotificationReadHandler::class)]
final readonly class MarkNotificationRead implements Command
{
    public function __construct(public string $communicationId) {}

    public function action(): string
    {
        return 'notification.read';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('communication', $this->communicationId);
    }
}
