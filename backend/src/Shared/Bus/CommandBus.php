<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditOutcome;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Outbox\Outbox;
use Fundly\Shared\Security\AccessDecision;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use ReflectionClass;

/**
 * The transactional command pipeline (TRD §4.1):
 *
 *   BEGIN → authorize → validate → handle → audit → outbox → COMMIT → events
 *
 * The order is fixed here, so no handler can skip authorisation or audit.
 * A denied authorisation is audited in its own transaction after rollback,
 * so the failed attempt is never lost (FR-AUD-007).
 */
final class CommandBus
{
    public function __construct(
        private readonly Container $container,
        private readonly ConnectionInterface $db,
        private readonly AuthorizationGate $gate,
        private readonly ResourceResolver $resources,
        private readonly ValidatorFactory $validator,
        private readonly AuditTrail $audit,
        private readonly Outbox $outbox,
        private readonly Dispatcher $events,
        private readonly CurrentPrincipal $current,
    ) {}

    public function dispatch(Command $command, Principal $principal): mixed
    {
        // The command runs as, and is audited as, the principal it was dispatched with.
        $previous = $this->current->get();
        $this->current->set($principal);
        try {
            return $this->run($command, $principal);
        } finally {
            $this->current->set($previous);
        }
    }

    private function run(Command $command, Principal $principal): mixed
    {
        try {
            [$result, $context] = $this->db->transaction(function () use ($command, $principal): array {
                [$decision, $resource] = $this->authorizeStage($command, $principal);
                $this->validateStage($command);
                $context = new CommandContext($principal, $decision, $resource);
                $result = $this->handleStage($command, $context);
                $this->auditStage($command, $context, $decision);
                $this->outboxStage($context);

                return [$result, $context];
            });
        } catch (AccessDenied $denied) {
            $this->recordDenial($command, $denied);
            throw $denied;
        }

        foreach ($context->events() as $event) {
            $this->events->dispatch($event);
        }

        return $result;
    }

    /** @return array{0: ?AccessDecision, 1: ?ResourceAttributes} */
    private function authorizeStage(Command $command, Principal $principal): array
    {
        $resource = $command->resource() === null ? null : $this->resources->resolve($command->resource());
        $permission = $command->permission();

        if ($permission === null) {
            if (! $principal->isSystem()) {
                throw new AccessDenied($command->action(), 'system_only', $resource, 'This action can only be performed by the platform.');
            }

            return [null, $resource];
        }

        return [$this->gate->authorize($principal, $permission, $resource), $resource];
    }

    private function validateStage(Command $command): void
    {
        if (! $command instanceof ValidatesInput) {
            return;
        }
        $validator = $this->validator->make($command->data(), $command->rules());
        if ($validator->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $validator->errors()->toArray();
            throw new ValidationFailed($errors);
        }
    }

    private function handleStage(Command $command, CommandContext $context): mixed
    {
        $attributes = (new ReflectionClass($command))->getAttributes(HandledBy::class);
        if ($attributes === []) {
            throw new LogicException(sprintf('Command %s has no #[HandledBy] handler.', $command::class));
        }
        $handler = $this->container->make($attributes[0]->newInstance()->handler);
        if (! $handler instanceof CommandHandler) {
            throw new LogicException(sprintf('Handler for %s must implement CommandHandler.', $command::class));
        }

        return $handler->handle($command, $context);
    }

    private function auditStage(Command $command, CommandContext $context, ?AccessDecision $decision): void
    {
        $entries = $context->auditEntries();
        if ($entries === []) {
            $ref = $command->resource();
            $entries = [new AuditEntry(
                action: $command->action(),
                entityType: $ref?->type,
                entityId: $ref?->id,
                permission: $command->permission(),
            )];
        }
        foreach ($entries as $entry) {
            $this->audit->record(new AuditEntry(
                action: $entry->action,
                outcome: $entry->outcome,
                entityType: $entry->entityType,
                entityId: $entry->entityId,
                before: $entry->before,
                after: $entry->after,
                permission: $entry->permission ?? $command->permission(),
                reasonCode: $entry->reasonCode ?? ($decision?->onBehalfOf !== null ? 'delegated' : null),
                reasonText: $entry->reasonText,
                stepUpRef: $entry->stepUpRef ?? $context->principal->stepUpRef,
            ));
        }
    }

    private function outboxStage(CommandContext $context): void
    {
        foreach ($context->outboxIntents() as $intent) {
            $this->outbox->add($intent);
        }
    }

    private function recordDenial(Command $command, AccessDenied $denied): void
    {
        $ref = $command->resource();
        $this->audit->record(new AuditEntry(
            action: 'authz.denied',
            outcome: AuditOutcome::Denied,
            entityType: $ref?->type,
            entityId: $ref?->id,
            after: ['command' => $command->action(), 'reason' => $denied->reason],
            permission: $denied->permission,
            reasonCode: $denied->reason,
        ));
    }
}
