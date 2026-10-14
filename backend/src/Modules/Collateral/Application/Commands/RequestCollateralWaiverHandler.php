<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\WaiveCollateralAction;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class RequestCollateralWaiverHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly ChangeRequestGateway $changeRequests) {}

    /** @return array{data: array<string, mixed>} the change request awaiting a checker */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RequestCollateralWaiver);
        $payload = ['target' => $command->target, 'id' => $command->id, 'reason' => (string) $command->reason];
        if ($command->target === 'insurance') {
            $c = Collateral::query()->lockForUpdate()->findOrFail($this->file->collateral($command->id)->id);
            $this->file->assertOpen($this->file->application($c->origin_application_id));
            WaiveCollateralAction::assertWaivable('insurance', $c->insurance_requirement);
            $cr = $this->changeRequests->submit(WaiveCollateralAction::TYPE, $payload, $command->reason, $context);
            $c->forceFill(['insurance_requirement' => 'waiver_pending', 'insurance_waiver_reason' => $command->reason, 'insurance_waiver_change_request_id' => self::id($cr)])->save();

            return ['data' => $cr];
        }
        $s = PerfectionStep::query()->lockForUpdate()->findOrFail($this->file->step($command->id)->id);
        $this->file->assertOpen($this->file->application($this->file->collateral($s->collateral_id)->origin_application_id));
        WaiveCollateralAction::assertWaivable('perfection_step', $s->status);
        $cr = $this->changeRequests->submit(WaiveCollateralAction::TYPE, $payload, $command->reason, $context);
        $s->forceFill(['status' => 'waiver_pending', 'waiver_reason' => $command->reason, 'waiver_change_request_id' => self::id($cr)])->save();

        return ['data' => $cr];
    }

    /** @param array<string, mixed> $cr */
    private static function id(array $cr): ?string
    {
        return is_string($cr['id'] ?? null) ? $cr['id'] : null;
    }
}
