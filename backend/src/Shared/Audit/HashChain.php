<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

use Fundly\Shared\Json\CanonicalJson;

/**
 * hash = SHA-256(prev_hash ‖ canonical_json(row without hash)) (TRD §5.4).
 * Pure: shared by the writer and the verifier so they cannot drift.
 */
final class HashChain
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Column order is irrelevant: canonical JSON sorts keys. */
    public const FIELDS = [
        'id', 'tenant_id', 'seq', 'occurred_at', 'actor_type', 'actor_id', 'actor_roles',
        'effective_permissions_hash', 'on_behalf_of', 'source_ip', 'user_agent', 'device_id',
        'action', 'permission', 'outcome', 'entity_type', 'entity_id', 'before', 'after',
        'reason_code', 'reason_text', 'correlation_id', 'step_up_ref', 'prev_hash',
    ];

    /** @param array<string, mixed> $row */
    public static function compute(array $row): string
    {
        $fields = [];
        foreach (self::FIELDS as $f) {
            $fields[$f] = $row[$f] ?? null;
        }
        $prev = is_string($fields['prev_hash']) ? $fields['prev_hash'] : self::GENESIS;

        return hash('sha256', $prev.CanonicalJson::encode($fields));
    }
}
