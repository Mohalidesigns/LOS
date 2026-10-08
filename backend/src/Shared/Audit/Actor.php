<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

use Fundly\Shared\Security\SystemIdentity;

/** Who performed an audited action, with a snapshot of their roles and permissions (FR-AUD-002). */
final readonly class Actor
{
    /** @param list<string> $roles */
    public function __construct(
        public ActorType $type,
        public string $id,
        public array $roles = [],
        public ?string $permissionsHash = null,
        public ?string $onBehalfOf = null,
    ) {
    }

    public static function system(SystemIdentity $identity): self
    {
        return new self(ActorType::System, $identity->value, [$identity->value]);
    }

    public static function anonymous(string $hint = 'anonymous'): self
    {
        return new self(ActorType::Anonymous, $hint);
    }

    public function onBehalfOf(?string $principal): self
    {
        return new self($this->type, $this->id, $this->roles, $this->permissionsHash, $principal);
    }
}
