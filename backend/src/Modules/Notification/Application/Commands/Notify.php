<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/**
 * Raise one business notification. `discriminator` makes the dedupe key
 * (e.g. the event version or instruction id); `context` adds template
 * variables and, under `exclude_user_ids` / `attachments`, routing data.
 * Platform-only.
 */
#[HandledBy(NotifyHandler::class)]
final readonly class Notify implements Command
{
    /** @param array<string, mixed> $context */
    public function __construct(public string $event, public string $applicationId, public string $discriminator, public array $context = []) {}

    public function action(): string
    {
        return 'notification.dispatched';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}
