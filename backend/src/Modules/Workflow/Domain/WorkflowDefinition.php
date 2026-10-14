<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Domain;

use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * A workflow (FR-WFL-001, TRD §6.1): tenant-configured stages per product
 * (bound by the product's `bindings.workflow`), each mapped to one
 * canonical status, with its assignment rule and SLA. A `workflow.definition`
 * configuration, maker-checker activated; an application stays on the
 * version it started on (FR-WFL-012).
 *
 *   { name, business_hours?: {timezone, days, start, end},
 *     stages: [ { code, name, status, automation?: "advance",
 *                 assignment?: { permission, strategy: queue|round_robin|least_loaded|originator },
 *                 sla?: { warn_hours, breach_hours, escalate_to? } } ] }
 *
 * On hold is not a stage: it pauses the current stage's clock (FR-WFL-008).
 */
final class WorkflowDefinition
{
    /** Canonical statuses a stage may map to (main path + rework). */
    public const STATUSES = ['submitted', 'pre_qualified', 'kyc_screening', 'documentation', 'assessment', 'recommended', 'approval', 'approved', 'counter_offered',
        'offer_issued', 'accepted', 'conditions_precedent', 'ready_for_disbursement', 'disbursing', 'returned_for_rework'];

    public const STRATEGIES = ['queue', 'round_robin', 'least_loaded', 'originator'];

    /** Stages the system may advance on its own (FR-WFL-011, D-038b: never auto-decline). */
    public const AUTOMATABLE = ['submitted', 'pre_qualified'];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        if (! is_string($content['name'] ?? null) || trim($content['name']) === '') {
            $e['content.name'][] = 'name is required.';
        }
        $hours = $content['business_hours'] ?? null;
        if ($hours !== null) {
            if (! is_array($hours)) {
                $e['content.business_hours'][] = 'business_hours must be an object.';
            } else {
                if (isset($hours['timezone']) && (! is_string($hours['timezone']) || ! in_array($hours['timezone'], timezone_identifiers_list(), true))) {
                    $e['content.business_hours.timezone'][] = 'timezone must be an IANA zone, e.g. Africa/Lagos.';
                }
                $days = $hours['days'] ?? [1, 2, 3, 4, 5];
                if (! is_array($days) || $days === [] || array_diff($days, [1, 2, 3, 4, 5, 6, 7]) !== []) {
                    $e['content.business_hours.days'][] = 'days lists ISO weekdays 1 (Monday) to 7.';
                }
                $start = $hours['start'] ?? '08:00';
                $end = $hours['end'] ?? '17:00';
                if (! is_string($start) || ! is_string($end) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end) !== 1 || $start >= $end) {
                    $e['content.business_hours'][] = 'start and end are HH:MM with start before end.';
                }
            }
        }
        $stages = $content['stages'] ?? null;
        if (! is_array($stages) || ! array_is_list($stages) || $stages === []) {
            throw ValidationFailed::with($e + ['content.stages' => 'stages must list the workflow stages.']);
        }
        $codes = [];
        $statuses = [];
        foreach ($stages as $i => $s) {
            $p = "content.stages.{$i}";
            if (! is_array($s)) {
                $e[$p][] = 'Each stage must be an object.';

                continue;
            }
            $code = $s['code'] ?? null;
            if (! is_string($code) || preg_match('/^[a-z][a-z0-9_]{1,47}$/', $code) !== 1) {
                $e["{$p}.code"][] = 'code must be lower-case snake_case.';
            } elseif (isset($codes[$code])) {
                $e["{$p}.code"][] = 'Stage codes must be unique.';
            } else {
                $codes[$code] = true;
            }
            if (! is_string($s['name'] ?? null) || trim($s['name']) === '' || mb_strlen($s['name']) > 80) {
                $e["{$p}.name"][] = 'name is required (max 80 characters).';
            }
            $status = $s['status'] ?? null;
            if (! in_array($status, self::STATUSES, true)) {
                $e["{$p}.status"][] = 'status must be a canonical status on the main path: '.implode(', ', self::STATUSES).'.';
            } elseif (isset($statuses[$status])) {
                $e["{$p}.status"][] = 'Each canonical status maps to one stage (P1; parallel tracks are P3).';
            } else {
                $statuses[$status] = true;
            }
            if (isset($s['automation']) && ($s['automation'] !== 'advance' || ! in_array($status, self::AUTOMATABLE, true))) {
                $e["{$p}.automation"][] = 'automation "advance" is allowed on submitted and pre_qualified only (automation never declines).';
            }
            if (isset($s['assignment'])) {
                $a = $s['assignment'];
                if (! is_array($a) || ! is_string($a['permission'] ?? null) || preg_match('/^[a-z_]+:[a-z_]+$/', $a['permission']) !== 1) {
                    $e["{$p}.assignment.permission"][] = 'permission is the permission that works the stage, e.g. credit:analyse.';
                }
                if (! is_array($a) || ! in_array($a['strategy'] ?? null, self::STRATEGIES, true)) {
                    $e["{$p}.assignment.strategy"][] = 'strategy must be one of: '.implode(', ', self::STRATEGIES).'.';
                }
                if (isset($s['automation'])) {
                    $e["{$p}.assignment"][] = 'An automated stage has no assignment.';
                }
            }
            if (isset($s['sla'])) {
                $sla = $s['sla'];
                $warn = is_array($sla) ? ($sla['warn_hours'] ?? null) : null;
                $breach = is_array($sla) ? ($sla['breach_hours'] ?? null) : null;
                if (! is_int($warn) || ! is_int($breach) || $warn < 1 || $breach > 2000 || $warn >= $breach) {
                    $e["{$p}.sla"][] = 'sla needs whole business hours with 1 ≤ warn_hours < breach_hours ≤ 2000.';
                }
                if (is_array($sla) && isset($sla['escalate_to']) && (! is_string($sla['escalate_to']) || preg_match('/^[a-z_]+:[a-z_]+$/', $sla['escalate_to']) !== 1)) {
                    $e["{$p}.sla.escalate_to"][] = 'escalate_to is a permission, e.g. task:reassign.';
                }
                if (! isset($s['assignment'])) {
                    $e["{$p}.sla"][] = 'An SLA needs an assignment: someone must own the clock.';
                }
            }
        }
        if ($e !== []) {
            throw ValidationFailed::with($e);
        }
    }

    public function name(): string
    {
        return (string) $this->content['name'];
    }

    public function calendar(): BusinessCalendar
    {
        return is_array($this->content['business_hours'] ?? null) ? BusinessCalendar::of($this->content['business_hours']) : BusinessCalendar::lagos();
    }

    /** @return array{code: string, name: string, status: string, automation: ?string, assignment: ?array{permission: string, strategy: string}, sla: ?array{warn_hours: int, breach_hours: int, escalate_to: string}}|null */
    public function stageFor(string $status): ?array
    {
        foreach ((array) ($this->content['stages'] ?? []) as $s) {
            if (is_array($s) && ($s['status'] ?? null) === $status) {
                $a = is_array($s['assignment'] ?? null) ? $s['assignment'] : null;
                $sla = is_array($s['sla'] ?? null) ? $s['sla'] : null;

                return [
                    'code' => (string) $s['code'], 'name' => (string) $s['name'], 'status' => $status,
                    'automation' => is_string($s['automation'] ?? null) ? $s['automation'] : null,
                    'assignment' => $a === null ? null : ['permission' => (string) $a['permission'], 'strategy' => (string) $a['strategy']],
                    'sla' => $sla === null ? null : ['warn_hours' => (int) $sla['warn_hours'], 'breach_hours' => (int) $sla['breach_hours'], 'escalate_to' => is_string($sla['escalate_to'] ?? null) ? $sla['escalate_to'] : 'task:reassign'],
                ];
            }
        }

        return null;
    }
}
