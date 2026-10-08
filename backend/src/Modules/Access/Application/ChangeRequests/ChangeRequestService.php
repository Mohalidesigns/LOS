<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\ChangeRequests;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Access\Infrastructure\Models\ChangeRequest;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ProblemException;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceResolver;

/**
 * Generic maker-checker engine (TRD §6.4, FR-SEC-007, FR-TEN-009):
 *
 *  - submit: maker permission, validate, record the state fingerprint;
 *  - approve: checker ≠ maker, checker not excluded, checker permission in
 *    scope, SoD at action time (via the gate), re-validate, fail stale if the
 *    fingerprint changed, then execute in the same transaction;
 *  - reject / cancel.
 */
final class ChangeRequestService implements ChangeRequestGateway
{
    public function __construct(
        private readonly ChangeActionRegistry $registry,
        private readonly AuthorizationGate $gate,
        private readonly ResourceResolver $resources,
        private readonly Clock $clock,
        private readonly RequestContext $request,
    ) {}

    public function submit(string $actionType, array $payload, ?string $reason, CommandContext $context): array
    {
        $action = $this->registry->get($actionType);
        $maker = $context->principal;
        $entity = $action->entity($payload);
        $this->gate->authorize($maker, $action->makerPermission(), $entity === null ? null : $this->resources->resolve($entity));
        $action->validate($payload, $maker);

        $cr = new ChangeRequest;
        $cr->forceFill([
            'action_type' => $actionType,
            'entity_type' => $entity?->type,
            'entity_id' => $entity?->id,
            'payload' => $payload,
            'payload_hash' => CanonicalJson::hash($payload),
            'state_fingerprint' => $action->fingerprint($payload),
            'required_checker_permission' => $action->checkerPermission(),
            'status' => 'pending',
            'maker_id' => $maker->id,
            'maker_reason' => $reason,
            'correlation_id' => $this->request->correlationId(),
        ])->save();

        $context->audit(new AuditEntry(
            action: 'change_request.submitted',
            entityType: 'change_request',
            entityId: $cr->id,
            after: ['action_type' => $actionType, 'payload' => $payload, 'target' => $entity === null ? null : ['type' => $entity->type, 'id' => $entity->id]],
            permission: $action->makerPermission(),
            reasonText: $reason,
        ));

        return self::present($cr);
    }

    /** @return array<string, mixed> */
    public function approve(string $id, ?string $reason, CommandContext $context): array
    {
        $cr = $this->lockPending($id);
        $action = $this->registry->get($cr->action_type);
        $checker = $context->principal;

        $this->assertIndependentChecker($cr, $checker, $action->excludedCheckers($cr->payload));
        $entity = $action->entity($cr->payload);
        $target = $entity === null ? null : $this->resources->resolve($entity);
        $this->gate->authorize($checker, $action->checkerPermission(), $target);

        $makerPrincipal = new Principal($cr->maker_id, $checker->tenantId, $checker->kind);
        try {
            $action->validate($cr->payload, $makerPrincipal);
        } catch (ProblemException $invalid) {
            return $this->finish($cr, 'failed', $checker, $reason, ['error' => $invalid->type(), 'detail' => $invalid->getMessage()], $context);
        }

        if (! hash_equals($cr->state_fingerprint, $action->fingerprint($cr->payload))) {
            return $this->finish($cr, 'failed_stale', $checker, $reason, ['error' => 'stale', 'detail' => 'The state this request was based on has changed since it was raised.'], $context);
        }

        $result = $action->execute($cr->payload, $cr->id, $context);
        $cr->forceFill(['executed_at' => $this->clock->now()]);

        return $this->finish($cr, 'executed', $checker, $reason, $result, $context);
    }

    /** @return array<string, mixed> */
    public function reject(string $id, string $reason, CommandContext $context): array
    {
        $cr = $this->lockPending($id);
        $action = $this->registry->get($cr->action_type);
        $checker = $context->principal;
        $this->assertIndependentChecker($cr, $checker, $action->excludedCheckers($cr->payload));
        $entity = $action->entity($cr->payload);
        $this->gate->authorize($checker, $action->checkerPermission(), $entity === null ? null : $this->resources->resolve($entity));

        return $this->finish($cr, 'rejected', $checker, $reason, null, $context);
    }

    /** @return array<string, mixed> */
    public function cancel(string $id, ?string $reason, CommandContext $context): array
    {
        $cr = $this->lockPending($id);
        if ($cr->maker_id !== $context->principal->id) {
            throw new AccessDenied('change_request:cancel', 'not_maker', null, 'Only the maker can cancel a change request.');
        }
        $cr->forceFill(['status' => 'cancelled', 'decision_reason' => $reason, 'decided_at' => $this->clock->now()])->save();
        $context->audit(new AuditEntry(action: 'change_request.cancelled', entityType: 'change_request', entityId: $cr->id, after: ['status' => 'cancelled'], reasonText: $reason));

        return self::present($cr);
    }

    /** @return array<string, mixed> */
    public static function present(ChangeRequest $cr): array
    {
        return [
            'id' => $cr->id,
            'action_type' => $cr->action_type,
            'status' => $cr->status,
            'entity_type' => $cr->entity_type,
            'entity_id' => $cr->entity_id,
            'payload' => $cr->payload,
            'required_checker_permission' => $cr->required_checker_permission,
            'maker_id' => $cr->maker_id,
            'maker_reason' => $cr->maker_reason,
            'checker_id' => $cr->checker_id,
            'decision_reason' => $cr->decision_reason,
            'execution_result' => $cr->execution_result,
            'created_at' => $cr->created_at->toIso8601ZuluString('microsecond'),
            'decided_at' => $cr->decided_at?->toIso8601ZuluString('microsecond'),
            'executed_at' => $cr->executed_at?->toIso8601ZuluString('microsecond'),
        ];
    }

    private function lockPending(string $id): ChangeRequest
    {
        $cr = ChangeRequest::query()->whereKey($id)->lockForUpdate()->first();
        if (! $cr instanceof ChangeRequest) {
            throw new NotFound('Change request not found.');
        }
        if ($cr->status !== 'pending') {
            throw new CodedConflict('change-request-not-pending', "Change request is {$cr->status}.");
        }

        return $cr;
    }

    /** @param list<string> $excluded */
    private function assertIndependentChecker(ChangeRequest $cr, Principal $checker, array $excluded): void
    {
        if ($cr->maker_id === $checker->id) {
            throw new AccessDenied($cr->required_checker_permission, 'sod_conflict', null, 'Four-eyes: the checker must be a different person from the maker.');
        }
        if (in_array($checker->id, $excluded, true)) {
            throw new AccessDenied($cr->required_checker_permission, 'sod_conflict', null, 'You cannot approve a change that concerns your own access.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    private function finish(ChangeRequest $cr, string $status, Principal $checker, ?string $reason, ?array $result, CommandContext $context): array
    {
        $cr->forceFill([
            'status' => $status,
            'checker_id' => $checker->id,
            'decision_reason' => $reason,
            'decided_at' => $this->clock->now(),
            'step_up_ref' => $checker->stepUpRef,
            'execution_result' => $result,
        ])->save();

        $context->audit(new AuditEntry(
            action: 'change_request.'.$status,
            entityType: 'change_request',
            entityId: $cr->id,
            after: ['action_type' => $cr->action_type, 'status' => $status, 'result' => $result],
            permission: $cr->required_checker_permission,
            reasonText: $reason,
            stepUpRef: $checker->stepUpRef,
        ));

        return self::present($cr);
    }
}
