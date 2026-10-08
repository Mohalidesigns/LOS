<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

use Fundly\Shared\Exceptions\DomainRuleViolation;

/**
 * Two-person rule for screening dispositions (FR-CMP-014/017): one officer
 * proposes, a different officer confirms; nobody who originated the
 * application may do either.
 */
final class FourEyes
{
    public static function assertCanPropose(AlertStatus $status, string $actorId, ?string $originatorId): void
    {
        if ($status !== AlertStatus::Open) {
            throw new DomainRuleViolation("The alert is {$status->value}; only open alerts take a proposed disposition.");
        }
        if ($originatorId !== null && $actorId === $originatorId) {
            throw new DomainRuleViolation('The originator of the application may not disposition its screening alerts (FR-CMP-014).');
        }
    }

    public static function assertCanConfirm(AlertStatus $status, string $actorId, ?string $proposerId, ?string $originatorId): void
    {
        if ($status !== AlertStatus::PendingConfirmation) {
            throw new DomainRuleViolation("The alert is {$status->value}; there is no proposed disposition to confirm.");
        }
        if ($actorId === $proposerId) {
            throw new DomainRuleViolation('A second, different officer must confirm the disposition (four-eyes).');
        }
        if ($originatorId !== null && $actorId === $originatorId) {
            throw new DomainRuleViolation('The originator of the application may not disposition its screening alerts (FR-CMP-014).');
        }
    }
}
