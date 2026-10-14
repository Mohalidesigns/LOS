<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** Users were @mentioned in a case note (FR-WFL-010). */
final readonly class NoteMentioned implements DomainEvent
{
    /** @param list<string> $userIds */
    public function __construct(public string $tenantId, public string $applicationId, public string $noteId, public string $authorId, public array $userIds) {}

    public function name(): string
    {
        return 'workflow.note_mentioned';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'note_id' => $this->noteId, 'author_id' => $this->authorId, 'user_ids' => $this->userIds];
    }
}
