<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\CursorPaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Overrides, waivers, exceptions and manual interventions across a period, by
 * user and by type (FR-AUD-016). Derived from the hash-chained audit trail,
 * which every owning module already writes to (D-086): no module tables are
 * read, and the report inherits the trail's tamper evidence. Maker-checker
 * waivers show the checker who approved them and the maker who asked.
 */
final class OverridesReport
{
    /** @var array<string, list<string>> */
    public const CATALOGUE = [
        'policy_exception' => ['credit.exception.raised'],
        'waiver' => ['condition.waived', 'document.checklist_item.waived', 'collateral.insurance_waived', 'collateral.perfection_waived'],
        'manual_intervention' => ['workflow.task_reassigned', 'workflow.migrated', 'disbursement.retried', 'reconciliation.break_resolved', 'compliance.screening_alert_resolved'],
    ];

    public function __construct(private readonly ConnectionInterface $db, private readonly AuthorityDirectory $directory) {}

    /** @return array<string, mixed> */
    public function search(Request $request): array
    {
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        $type = isset($filter['type']) && is_string($filter['type']) && $filter['type'] !== '' ? $filter['type'] : null;
        if ($type !== null && ! isset(self::CATALOGUE[$type])) {
            throw ValidationFailed::with(['filter[type]' => 'Must be one of '.implode(', ', array_keys(self::CATALOGUE)).'.']);
        }
        $actions = $type === null ? array_merge(...array_values(self::CATALOGUE)) : self::CATALOGUE[$type];
        $base = function () use ($actions, $filter): Builder {
            $q = $this->db->table('audit_events as e')->whereIn('e.action', $actions)->where('e.outcome', 'success');
            if (isset($filter['actor_id']) && is_string($filter['actor_id']) && $filter['actor_id'] !== '') {
                $q->where('e.actor_id', $filter['actor_id']);
            }
            foreach (['from' => '>=', 'to' => '<'] as $f => $op) {
                if (isset($filter[$f]) && is_string($filter[$f]) && $filter[$f] !== '') {
                    if (strtotime($filter[$f]) === false) {
                        throw ValidationFailed::with(["filter[{$f}]" => 'Must be an ISO 8601 timestamp.']);
                    }
                    $q->where('e.occurred_at', $op, $filter[$f]);
                }
            }

            return $q;
        };

        $page = $base()
            ->leftJoin('audit_events as cr', function ($j): void {
                $j->where('cr.action', 'change_request.submitted')->where('cr.entity_type', 'change_request')
                    ->whereRaw("cr.entity_id = e.\"after\"->>'change_request_id'");
            })
            ->select(['e.id', 'e.seq', 'e.occurred_at', 'e.action', 'e.entity_type', 'e.entity_id', 'e.actor_id', 'e.reason_code', 'e.reason_text', 'e.after', 'cr.actor_id as requested_by', 'cr.reason_text as request_reason']);
        $result = CursorPaginator::paginate($this->db->table('audit_events')->newQuery()->fromSub($page, 'o'), $request, static fn (\stdClass $r): array => (array) $r, 'seq');
        /** @var list<array<string, mixed>> $rows */
        $rows = $result['data'];

        $summary = $base()->selectRaw('e.action, e.actor_id, count(*) as n')->groupBy('e.action', 'e.actor_id')->get()->all();
        $ids = array_merge(array_column($rows, 'actor_id'), array_column($rows, 'requested_by'), array_map(static fn (\stdClass $r): string => (string) $r->actor_id, $summary));
        $names = $this->directory->names(array_values(array_filter(array_map('strval', $ids))));
        $person = static fn (?string $id): ?array => $id === null ? null : ['id' => $id, 'name' => $names[$id] ?? null];

        $byType = array_fill_keys(array_keys(self::CATALOGUE), 0);
        $byUser = [];
        foreach ($summary as $s) {
            $byType[self::typeOf((string) $s->action)] += (int) $s->n;
            $uid = (string) $s->actor_id;
            $byUser[$uid] ??= ['user' => $person($uid), 'count' => 0];
            $byUser[$uid]['count'] += (int) $s->n;
        }
        usort($byUser, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $result['data'] = array_map(static function (array $row) use ($person): array {
            $r = (object) $row;
            $after = is_string($r->after) ? json_decode($r->after, true) : null;
            $after = is_array($after) ? $after : [];
            $applicationId = $r->entity_type === 'application' ? $r->entity_id : (is_string($after['application_id'] ?? null) ? $after['application_id'] : null);

            return [
                'id' => $r->id,
                'occurred_at' => AuditQueries::ts((string) $r->occurred_at),
                'type' => self::typeOf((string) $r->action),
                'action' => $r->action,
                'entity_type' => $r->entity_type,
                'entity_id' => $r->entity_id,
                'application_id' => $applicationId,
                'performed_by' => $person((string) $r->actor_id),
                'requested_by' => $person(is_string($r->requested_by) ? $r->requested_by : null),
                'change_request_id' => is_string($after['change_request_id'] ?? null) ? $after['change_request_id'] : null,
                'reason_code' => $r->reason_code,
                'reason' => $r->reason_text ?? $r->request_reason,
                'details' => $after,
            ];
        }, $rows);
        $result['meta']['summary'] = ['by_type' => $byType, 'by_user' => $byUser];

        return $result;
    }

    private static function typeOf(string $action): string
    {
        foreach (self::CATALOGUE as $type => $actions) {
            if (in_array($action, $actions, true)) {
                return $type;
            }
        }

        return 'manual_intervention';
    }
}
