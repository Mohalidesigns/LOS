<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Domain\Cover;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralAllocation;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;

final class CreateCollateralHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly PartyDirectory $parties, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateCollateral);
        $app = $this->file->application($command->applicationId);
        $this->file->assertOpen($app);
        [$type, $source, $versionId] = $this->file->type((string) $command->typeCode);
        $owner = $command->ownerPartyId === null ? null : $this->parties->find($command->ownerPartyId);
        if ($command->ownerPartyId !== null && $owner === null) {
            throw ValidationFailed::with(['owner_party_id' => 'The owner must be a recorded party.']);
        }
        $ownerName = $command->ownerName ?? $owner?->displayName;
        if ($ownerName === null) {
            throw ValidationFailed::with(['owner_name' => 'Give the owner: a recorded party or a name (third-party security).']);
        }
        $currency = $this->file->exposure($app)->currency;
        $market = Money::of((string) $command->marketValue, $currency);
        if ($market->isZero()) {
            throw ValidationFailed::with(['market_value' => 'The value must be greater than zero.']);
        }
        $fsv = Cover::forcedSaleValue($market, $type->haircutPercent());
        $policy = $this->file->policy($app);
        $now = $this->file->now();

        $c = new Collateral;
        $c->forceFill([
            'origin_application_id' => $app->id, 'type_code' => $command->typeCode, 'type_source' => $source, 'type_version_id' => $versionId,
            'category' => $type->category(), 'description' => trim((string) $command->description),
            'owner_party_id' => $command->ownerPartyId, 'owner_name' => $ownerName, 'state_code' => $command->stateCode,
            'currency' => $currency->code, 'market_value' => $market->toStorage(), 'haircut_percent' => (string) $type->haircutPercent()->toScale(4), 'forced_sale_value' => $fsv->toStorage(),
            'value_basis' => 'declared', 'requires_valuation' => $type->requiresValuation(), 'valuation_valid_until' => null,
            'insurance_requirement' => $type->requiresInsurance() ? 'required' : 'not_required', 'status' => 'active', 'created_by' => $context->principal->id,
        ])->save();
        $steps = [];
        foreach ($type->perfectionSteps($command->stateCode) as $i => $s) {
            $step = new PerfectionStep;
            $step->forceFill([
                'collateral_id' => $c->id, 'sequence' => $i + 1, 'code' => $s['code'], 'name' => $s['name'], 'timing' => $policy->timing($s['code'], $s['timing']),
                'due_date' => $s['due_days'] === null ? null : $now->copy()->startOfDay()->addDays($s['due_days'])->toDateString(), 'status' => 'pending',
            ])->save();
            $steps[] = ['code' => $step->code, 'timing' => $step->timing];
        }
        $a = new CollateralAllocation;
        $a->forceFill(['collateral_id' => $c->id, 'application_id' => $app->id, 'amount' => $fsv->toStorage(), 'currency' => $currency->code, 'status' => 'active', 'created_by' => $context->principal->id, 'updated_by' => $context->principal->id])->save();

        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, after: [
            'application_id' => $app->id, 'type_code' => $c->type_code, 'type_source' => $source, 'type_version_id' => $versionId, 'description' => $c->description,
            'owner_party_id' => $c->owner_party_id, 'market_value' => $market->toArray(), 'haircut_percent' => $c->haircut_percent, 'forced_sale_value' => $fsv->toArray(),
            'perfection_steps' => $steps, 'allocated' => $fsv->toArray(),
        ]));

        return ['data' => $this->queries->item($c)];
    }
}
