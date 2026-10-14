<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;

final class MarkAllNotificationsReadHandler implements CommandHandler
{
    public function __construct(private readonly Clock $clock) {}

    /** @return array{data: array{marked: int}} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof MarkAllNotificationsRead);
        $n = Communication::query()->where('channel', 'in_app')->where('recipient_user_id', $context->principal->id)->whereNull('read_at')
            ->update(['status' => 'read', 'read_at' => $this->clock->now()]);

        return ['data' => ['marked' => $n]];
    }
}
