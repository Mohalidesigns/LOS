<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Mark all my in-app notifications read. */
#[HandledBy(MarkAllNotificationsReadHandler::class)]
final readonly class MarkAllNotificationsRead implements Command
{
    public function __construct() {}

    public function action(): string
    {
        return 'notification.read_all';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }
}
