<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Application\TaskQueries;
use Fundly\Modules\Workflow\Infrastructure\Models\TaskView;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;

final class SaveTaskViewHandler implements CommandHandler
{
    public function __construct(private readonly TaskQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SaveTaskView);
        $me = $context->principal->id;
        $name = trim((string) $command->name);
        $v = TaskView::query()->where('user_id', $me)->where('name', $name)->first() ?? new TaskView;
        if (! $v->exists && TaskView::query()->where('user_id', $me)->count() >= 20) {
            throw ValidationFailed::with(['name' => 'You can keep up to 20 saved views; delete one first.']);
        }
        $filters = array_filter(array_intersect_key($command->filters, array_flip(['view', 'stage', 'sla'])), static fn (mixed $f): bool => is_string($f) && $f !== '');
        $v->forceFill(['user_id' => $me, 'name' => $name, 'filters' => $filters])->save();

        return ['data' => $this->queries->view($v)];
    }
}
