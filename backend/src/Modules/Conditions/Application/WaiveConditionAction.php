<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * Waiving a condition is the only way past an outstanding mandatory CP
 * (FR-CPR-002), and it runs through maker-checker at the configured
 * authority (`condition:waive_approve`). After a rejection the item stays
 * outstanding (waiver_pending) and can be satisfied or a new waiver requested.
 */
final class WaiveConditionAction implements ChangeAction
{
    public const TYPE = 'condition.waive';

    public function __construct(private readonly ConditionFile $file) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return 'condition:manage';
    }

    public function checkerPermission(): string
    {
        return 'condition:waive_approve';
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('condition', Payload::string($payload, 'condition_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $status = ConditionStatus::from($this->condition($payload)->status);
        if ($status !== ConditionStatus::Open && $status !== ConditionStatus::WaiverPending) {
            throw new DomainRuleViolation("This condition is already {$status->value}.");
        }
    }

    public function fingerprint(array $payload): string
    {
        $c = $this->condition($payload);

        return hash('sha256', $c->id.'|'.$c->description.'|'.($c->mandatory ? '1' : '0'));
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $c = $this->condition($payload);
        $before = ['status' => $c->status];
        $c->forceFill(['status' => ConditionStatus::Waived->value, 'waiver_reason' => Payload::string($payload, 'reason'), 'waiver_change_request_id' => $changeRequestId, 'signed_off_by' => $context->principal->id, 'signed_off_at' => $this->file->now()])->save();
        $context->audit(new AuditEntry(action: 'condition.waived', entityType: 'condition', entityId: $c->id, before: $before, after: [
            'status' => $c->status, 'kind' => $c->kind, 'mandatory' => $c->mandatory, 'change_request_id' => $changeRequestId,
        ], reasonText: Payload::string($payload, 'reason')));
        $this->file->advanceIfReady($c->application_id, $context->principal);

        return ['condition_id' => $c->id, 'status' => $c->status];
    }

    public function excludedCheckers(array $payload): array
    {
        return [];
    }

    /** @param array<string, mixed> $payload */
    private function condition(array $payload): Condition
    {
        return Condition::query()->find(Payload::optionalString($payload, 'condition_id')) ?? throw new NotFound('Condition not found.');
    }
}
