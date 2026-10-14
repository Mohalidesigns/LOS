<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

use DateTimeImmutable;

/**
 * The regulatory rule in force for a booking legal entity (FR-CMP-001/003,
 * LOS-FR-310): resolved from its jurisdiction × licence category to the
 * active jurisdiction pack, then by effective date.
 */
interface RegulatoryRules
{
    /** null when no pack is active for the entity or the pack has no such rule on that date */
    public function rule(string $legalEntityId, string $code, ?DateTimeImmutable $at = null): ?RegulatoryRule;
}
