<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Security\AccessDecision;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

/** Collects what a handler wants persisted alongside its state change. */
final class CommandContext
{
    /** @var list<AuditEntry> */
    private array $audit = [];

    /** @var list<DomainEvent> */
    private array $events = [];

    /** @var list<OutboxIntent> */
    private array $outbox = [];

    public function __construct(
        public readonly Principal $principal,
        public readonly ?AccessDecision $decision = null,
        public readonly ?ResourceAttributes $resource = null,
    ) {
    }

    public function audit(AuditEntry $entry): void
    {
        $this->audit[] = $entry;
    }

    public function raise(DomainEvent $event): void
    {
        $this->events[] = $event;
    }

    public function outbox(OutboxIntent $intent): void
    {
        $this->outbox[] = $intent;
    }

    /** @return list<AuditEntry> */
    public function auditEntries(): array
    {
        return $this->audit;
    }

    /** @return list<DomainEvent> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return list<OutboxIntent> */
    public function outboxIntents(): array
    {
        return $this->outbox;
    }
}
