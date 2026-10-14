<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Modules\Notification\Application\NotificationQueries;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;

final class MarkNotificationReadHandler implements CommandHandler
{
    public function __construct(private readonly Clock $clock, private readonly NotificationQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof MarkNotificationRead);
        // only the recipient's own in-app notifications are visible to them
        $c = Communication::query()->where('channel', 'in_app')->where('recipient_user_id', $context->principal->id)->find($command->communicationId) ?? throw new NotFound('Notification not found.');
        if ($c->read_at === null) {
            $c->forceFill(['status' => 'read', 'read_at' => $this->clock->now()])->save();
        }

        return ['data' => $this->queries->inboxItem($c)];
    }
}
