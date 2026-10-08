<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\CursorPaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;

/** Audit explorer search (FR-AUD-009 groundwork). Read-only; RLS scopes it to the tenant. */
final class AuditQueries
{
    private const FILTERS = ['entity_type', 'entity_id', 'actor_id', 'action', 'correlation_id', 'outcome', 'permission'];

    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /** @return array<string, mixed> */
    public function search(Request $request): array
    {
        $q = $this->db->table('audit_events');
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        foreach (self::FILTERS as $f) {
            if (isset($filter[$f]) && is_string($filter[$f]) && $filter[$f] !== '') {
                $q->where($f, $filter[$f]);
            }
        }
        foreach (['from' => '>=', 'to' => '<'] as $f => $op) {
            if (isset($filter[$f]) && is_string($filter[$f])) {
                if (strtotime($filter[$f]) === false) {
                    throw ValidationFailed::with(["filter[{$f}]" => 'Must be an ISO 8601 timestamp.']);
                }
                $q->where('occurred_at', $op, $filter[$f]);
            }
        }

        return CursorPaginator::paginate($q, $request, static fn (object $r): array => self::present($r), 'seq');
    }

    /** @return array<string, mixed> */
    public static function present(object $r): array
    {
        $json = static fn (mixed $v): mixed => is_string($v) ? json_decode($v, true) : null;

        return [
            'id' => $r->id,
            'seq' => (int) $r->seq,
            'occurred_at' => (new \DateTimeImmutable((string) $r->occurred_at))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'actor_type' => $r->actor_type,
            'actor_id' => $r->actor_id,
            'actor_roles' => $json($r->actor_roles) ?? [],
            'effective_permissions_hash' => $r->effective_permissions_hash,
            'on_behalf_of' => $r->on_behalf_of,
            'source_ip' => $r->source_ip,
            'user_agent' => $r->user_agent,
            'action' => $r->action,
            'permission' => $r->permission,
            'outcome' => $r->outcome,
            'entity_type' => $r->entity_type,
            'entity_id' => $r->entity_id,
            'before' => $json($r->before),
            'after' => $json($r->after),
            'reason_code' => $r->reason_code,
            'reason_text' => $r->reason_text,
            'correlation_id' => $r->correlation_id,
            'step_up_ref' => $r->step_up_ref,
            'prev_hash' => $r->prev_hash,
            'hash' => $r->hash,
        ];
    }
}
