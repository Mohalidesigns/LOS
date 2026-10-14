<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Application\SecurityAssessor;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralAllocation;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AuthorizationGate;

/**
 * One item can secure several facilities (FR-COL-005). The item row is
 * locked while the split is checked, so two concurrent allocations cannot
 * together exceed its lending value.
 */
final class AllocateCollateralHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly SecurityAssessor $assessor, private readonly AuthorizationGate $gate, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AllocateCollateral);
        $c = Collateral::query()->lockForUpdate()->findOrFail($this->file->collateral($command->collateralId)->id);
        if ($c->status !== 'active') {
            throw new DomainRuleViolation('This collateral has been released.');
        }
        $target = $this->file->application((string) $command->applicationId);
        // the maker needs authority over the facility being secured as well as the item
        $this->gate->authorize($context->principal, 'collateral:manage', $this->file->resource($target, 'application', $target->id));
        $this->file->assertOpen($target);
        if ($this->file->exposure($target)->currency->code !== $c->currency) {
            throw ValidationFailed::with(['application_id' => "The facility is not in {$c->currency}; cross-currency security needs FX haircuts, which are not configured yet."]);
        }
        $amount = Money::of((string) $command->amount, $c->currency);
        $others = $this->assessor->allocatedTotal($c, $target->id);
        $available = $c->forcedSale()->minus($others);
        if ($amount->isGreaterThan($available)) {
            throw ValidationFailed::with(['amount' => 'Over-allocation: '.$available->toStorage()." {$c->currency} of lending value is available on this item (FR-COL-005)."]);
        }
        $a = CollateralAllocation::query()->where('collateral_id', $c->id)->where('application_id', $target->id)->first() ?? new CollateralAllocation;
        $before = $a->exists ? ['amount' => $a->money()->toArray(), 'status' => $a->status] : null;
        $a->forceFill([
            'collateral_id' => $c->id, 'application_id' => $target->id, 'amount' => $amount->toStorage(), 'currency' => $c->currency,
            'status' => $amount->isZero() ? 'released' : 'active', 'updated_by' => $context->principal->id,
        ]);
        if (! $a->exists) {
            $a->created_by = $context->principal->id;
        }
        $a->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, before: $before, after: [
            'allocation_id' => $a->id, 'application_id' => $target->id, 'amount' => $amount->toArray(), 'status' => $a->status, 'allocated_elsewhere' => $others->toArray(),
        ]));

        return ['data' => $this->queries->item($c)];
    }
}
