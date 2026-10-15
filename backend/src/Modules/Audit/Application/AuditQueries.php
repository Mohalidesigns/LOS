<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\CursorPaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Audit explorer search (FR-AUD-009). Read-only; RLS scopes it to the tenant.
 * `filter[action]` ending in `*` is a prefix match; `filter[application_id]`
 * covers the application itself, events naming it in before/after, and every
 * event sharing a correlation id with an event on it (D-085).
 */
final class AuditQueries
{
    private const FILTERS = ['entity_type', 'entity_id', 'actor_id', 'action', 'correlation_id', 'outcome', 'permission', 'actor_type'];

    public function __construct(private readonly ConnectionInterface $db) {}

    /** @return array<string, mixed> */
    public function search(Request $request): array
    {
        $q = $this->db->table('audit_events');
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        foreach (self::FILTERS as $f) {
            if (isset($filter[$f]) && is_string($filter[$f]) && $filter[$f] !== '') {
                if ($f === 'action' && str_ends_with($filter[$f], '*')) {
                    $q->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], rtrim($filter[$f], '*')).'%');
                } else {
                    $q->where($f, $filter[$f]);
                }
            }
        }
        if (isset($filter['application_id']) && is_string($filter['application_id']) && $filter['application_id'] !== '') {
            $app = $filter['application_id'];
            $q->where(function (Builder $w) use ($app): void {
                $w->where(fn (Builder $x) => $x->where('entity_type', 'application')->where('entity_id', $app))
                    ->orWhereRaw("\"after\"->>'application_id' = ?", [$app])
                    ->orWhereRaw("\"before\"->>'application_id' = ?", [$app])
                    ->orWhereIn('correlation_id', fn (Builder $c) => $c->select('correlation_id')->from('audit_events')
                        ->where('entity_type', 'application')->where('entity_id', $app)->whereNotNull('correlation_id'));
            });
        }
        self::period($q, $filter);

        return CursorPaginator::paginate($q, $request, static fn (\stdClass $r): array => self::present($r), 'seq');
    }

    /**
     * `filter[from]` (inclusive) and `filter[to]` (exclusive) on occurred_at.
     *
     * @param  array<array-key, mixed>  $filter
     */
    public static function period(Builder $q, array $filter): void
    {
        foreach (['from' => '>=', 'to' => '<'] as $f => $op) {
            if (isset($filter[$f]) && is_string($filter[$f]) && $filter[$f] !== '') {
                if (strtotime($filter[$f]) === false) {
                    throw ValidationFailed::with(["filter[{$f}]" => 'Must be an ISO 8601 timestamp.']);
                }
                $q->where('occurred_at', $op, $filter[$f]);
            }
        }
    }

    public static function ts(string $value): string
    {
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @return array<string, mixed> */
    public static function present(\stdClass $r): array
    {
        $json = static fn (mixed $v): mixed => is_string($v) ? json_decode($v, true) : null;

        return [
            'id' => $r->id,
            'seq' => (int) $r->seq,
            'occurred_at' => self::ts((string) $r->occurred_at),
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
