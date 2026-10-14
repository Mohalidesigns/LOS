<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * The exception route past an unmet perfection step or insurance
 * requirement (FR-COL-008): maker-checker at the authority that waives
 * conditions (`condition:waive_approve`, step-up on the approve route).
 * After a rejection the item stays waiver_pending, which still blocks
 * disbursement; it can be completed or a new waiver requested.
 */
final class WaiveCollateralAction implements ChangeAction
{
    public const TYPE = 'collateral.waive';

    public function __construct(private readonly CollateralFile $file) {}

    public static function assertWaivable(string $target, string $status): void
    {
        $ok = $target === 'insurance' ? in_array($status, ['required', 'waiver_pending'], true) : in_array($status, PerfectionStep::OPEN, true);
        if (! $ok) {
            throw new DomainRuleViolation($target === 'insurance' ? "Insurance is {$status} on this item; there is nothing to waive." : "This step is already {$status}.");
        }
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return 'collateral:manage';
    }

    public function checkerPermission(): string
    {
        return 'condition:waive_approve';
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef(Payload::string($payload, 'target') === 'insurance' ? 'collateral' : 'perfection_step', Payload::string($payload, 'id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        self::assertWaivable(Payload::string($payload, 'target'), $this->status($payload));
    }

    public function fingerprint(array $payload): string
    {
        if (Payload::string($payload, 'target') === 'insurance') {
            $c = $this->collateral($payload);

            return hash('sha256', "insurance|{$c->id}|{$c->description}");
        }
        $s = $this->step($payload);

        return hash('sha256', "step|{$s->id}|{$s->name}|{$s->timing}");
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $reason = Payload::string($payload, 'reason');
        if (Payload::string($payload, 'target') === 'insurance') {
            $c = $this->collateral($payload);
            $before = ['insurance_requirement' => $c->insurance_requirement];
            $c->forceFill(['insurance_requirement' => 'waived', 'insurance_waiver_reason' => $reason, 'insurance_waiver_change_request_id' => $changeRequestId])->save();
            $context->audit(new AuditEntry(action: 'collateral.insurance_waived', entityType: 'collateral', entityId: $c->id, before: $before, after: ['insurance_requirement' => 'waived', 'change_request_id' => $changeRequestId], reasonText: $reason));

            return ['collateral_id' => $c->id, 'insurance_requirement' => 'waived'];
        }
        $s = $this->step($payload);
        $before = ['status' => $s->status];
        $s->forceFill(['status' => 'waived', 'waiver_reason' => $reason, 'waiver_change_request_id' => $changeRequestId, 'completed_by' => $context->principal->id, 'completed_at' => $this->file->now()])->save();
        $context->audit(new AuditEntry(action: 'collateral.perfection_waived', entityType: 'collateral', entityId: $s->collateral_id, before: $before, after: [
            'step_id' => $s->id, 'code' => $s->code, 'timing' => $s->timing, 'status' => 'waived', 'change_request_id' => $changeRequestId,
        ], reasonText: $reason));

        return ['step_id' => $s->id, 'status' => 'waived'];
    }

    public function excludedCheckers(array $payload): array
    {
        return [];
    }

    /** @param array<string, mixed> $payload */
    private function status(array $payload): string
    {
        return Payload::string($payload, 'target') === 'insurance' ? $this->collateral($payload)->insurance_requirement : $this->step($payload)->status;
    }

    /** @param array<string, mixed> $payload */
    private function collateral(array $payload): Collateral
    {
        return Collateral::query()->find(Payload::optionalString($payload, 'id')) ?? throw new NotFound('Collateral not found.');
    }

    /** @param array<string, mixed> $payload */
    private function step(array $payload): PerfectionStep
    {
        return PerfectionStep::query()->find(Payload::optionalString($payload, 'id')) ?? throw new NotFound('Perfection step not found.');
    }
}
