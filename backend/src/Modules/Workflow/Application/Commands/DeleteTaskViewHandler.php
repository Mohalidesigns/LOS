<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Infrastructure\Models\TaskView;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;

final class DeleteTaskViewHandler implements CommandHandler
{
    /** @return array{deleted: true} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeleteTaskView);
        $v = preg_match('/^[0-9a-f-]{36}$/', $command->viewId) === 1 ? TaskView::query()->where('user_id', $context->principal->id)->find($command->viewId) : null;
        ($v ?? throw new NotFound('Saved view not found.'))->delete();

        return ['deleted' => true];
    }
}
