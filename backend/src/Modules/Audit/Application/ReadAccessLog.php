<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use DateTimeZone;
use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;

/**
 * Read-access log (FR-AUD-006, TRD §5.4): one append-only row per successful
 * read of application or PII data, with the purpose the client declares
 * (`X-Access-Purpose`) when the workflow provides one. Written synchronously
 * in the request (D-084), not hash-chained: it is evidence of access, the
 * audit trail stays the evidence of change.
 */
final class ReadAccessLog
{
    private const FILTERS = ['actor_id', 'resource_type', 'resource_id', 'purpose', 'correlation_id'];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly RequestContext $request,
        private readonly ActorProvider $actors,
        private readonly Clock $clock,
    ) {}

    public function record(string $resourceType, ?string $resourceId, string $route, bool $containsPii, ?string $purpose): void
    {
        $actor = $this->actors->current();
        $this->db->table('pii_access_log')->insert([
            'id' => UuidV7::generate(),
            'tenant_id' => $this->tenant->requireId(),
            'occurred_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
            'actor_type' => $actor->type->value,
            'actor_id' => $actor->id,
            'on_behalf_of' => $actor->onBehalfOf,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : mb_substr($resourceId, 0, 128),
            'route' => mb_substr($route, 0, 128),
            'contains_pii' => $containsPii,
            'purpose' => $purpose,
            'source_ip' => $this->request->sourceIp(),
            'correlation_id' => $this->request->correlationId(),
        ]);
    }

    /** @return array<string, mixed> */
    public function search(Request $request): array
    {
        $q = $this->db->table('pii_access_log');
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        foreach (self::FILTERS as $f) {
            if (isset($filter[$f]) && is_string($filter[$f]) && $filter[$f] !== '') {
                $q->where($f, $filter[$f]);
            }
        }
        if (($filter['pii'] ?? null) === 'true') {
            $q->where('contains_pii', true);
        }
        AuditQueries::period($q, $filter);

        return CursorPaginator::paginate($q, $request, static fn (\stdClass $r): array => [
            'id' => $r->id,
            'occurred_at' => AuditQueries::ts((string) $r->occurred_at),
            'actor_type' => $r->actor_type,
            'actor_id' => $r->actor_id,
            'on_behalf_of' => $r->on_behalf_of,
            'resource_type' => $r->resource_type,
            'resource_id' => $r->resource_id,
            'route' => $r->route,
            'contains_pii' => (bool) $r->contains_pii,
            'purpose' => $r->purpose,
            'source_ip' => $r->source_ip,
            'correlation_id' => $r->correlation_id,
        ]);
    }

    /** Purpose codes are short snake_case tokens; anything else is refused rather than stored. */
    public static function purpose(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', trim($raw)) !== 1) {
            throw ValidationFailed::with(['X-Access-Purpose' => 'Must be a snake_case purpose code.']);
        }

        return trim($raw);
    }
}
