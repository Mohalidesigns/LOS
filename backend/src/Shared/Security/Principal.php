<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use DateTimeImmutable;

/**
 * The authenticated caller. Staff (session), service/partner accounts (token)
 * and named system identities are all principals evaluated by the same
 * authorisation function (FR-SEC-014): there is no privileged bypass for API
 * callers. System principals represent the platform itself acting inside a
 * tenant (outbox, scheduler) and are attributed by name in the audit trail.
 */
final readonly class Principal
{
    /**
     * @param  list<string>|null  $tokenAbilities  null for session principals; a token can only narrow access
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public PrincipalKind $kind,
        public ?array $tokenAbilities = null,
        public ?DateTimeImmutable $stepUpAt = null,
        public ?string $stepUpRef = null,
    ) {
    }

    public static function system(string $tenantId, SystemIdentity $identity): self
    {
        return new self($identity->value, $tenantId, PrincipalKind::System);
    }

    public function isSystem(): bool
    {
        return $this->kind === PrincipalKind::System;
    }

    public function viaToken(): bool
    {
        return $this->tokenAbilities !== null;
    }

    public function tokenAllows(string $permission): bool
    {
        if ($this->tokenAbilities === null) {
            return true;
        }

        return in_array('*', $this->tokenAbilities, true) || in_array($permission, $this->tokenAbilities, true);
    }

    public function steppedUpWithin(int $minutes, DateTimeImmutable $now): bool
    {
        return $this->stepUpAt !== null
            && $this->stepUpAt->getTimestamp() >= $now->getTimestamp() - ($minutes * 60);
    }
}
