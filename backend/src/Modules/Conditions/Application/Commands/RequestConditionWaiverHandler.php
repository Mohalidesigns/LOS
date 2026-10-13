<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Application\WaiveConditionAction;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class RequestConditionWaiverHandler implements CommandHandler
{
    public function __construct(private readonly ConditionFile $file, private readonly ChangeRequestGateway $changeRequests) {}

    /** @return array{data: array<string, mixed>} the change request awaiting a checker */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RequestConditionWaiver);
        $c = Condition::query()->lockForUpdate()->findOrFail($this->file->condition($command->conditionId)->id);
        $this->file->assertWorkable($this->file->application($c->application_id), $c->kind);
        ConditionStatus::from($c->status)->assertCanWaive();
        $cr = $this->changeRequests->submit(WaiveConditionAction::TYPE, ['condition_id' => $c->id, 'reason' => (string) $command->reason], $command->reason, $context);
        $c->forceFill(['status' => ConditionStatus::WaiverPending->value, 'waiver_reason' => $command->reason, 'waiver_change_request_id' => is_string($cr['id'] ?? null) ? $cr['id'] : null])->save();

        return ['data' => $cr];
    }
}
