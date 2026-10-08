<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application;

use DateTimeImmutable;
use Exception;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Infrastructure\ApplicationStore;
use Fundly\Modules\Application\Infrastructure\Models\ApplicantRecord;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApplicationQueries
{
    public const READ = 'application:view';

    public function __construct(
        private readonly ListScopeFilter $scope,
        private readonly AuthorizationGate $gate,
        private readonly ApplicationStore $store,
        private readonly ProductCatalogue $products,
    ) {}

    public static function etag(string $id, int $version): string
    {
        return ETag::of($id, $version);
    }

    public static function scopeColumns(): ScopeColumns
    {
        return new ScopeColumns(legalEntity: 'legal_entity_id', orgUnit: 'org_unit_id', product: 'product_id', currency: 'currency', amount: 'requested_amount', segment: 'segment');
    }

    public static function attributes(ApplicationRecord $r): ResourceAttributes
    {
        return new ResourceAttributes(
            legalEntityId: $r->legal_entity_id,
            orgUnitId: $r->org_unit_id,
            productId: $r->product_id,
            currency: $r->currency,
            amount: $r->requested_amount === null ? null : Money::of($r->requested_amount, $r->currency),
            segment: $r->segment,
            entityType: 'application',
            entityId: $r->id,
        );
    }

    /** @return array<string, mixed> */
    public function list(Request $request, Principal $principal): array
    {
        $q = ApplicationRecord::query();
        $this->scope->apply($q, $principal, self::READ, self::scopeColumns());
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        if (isset($filter['status']) && is_string($filter['status'])) {
            $statuses = array_values(array_filter(explode(',', $filter['status']), static fn (string $s): bool => CanonicalStatus::tryFrom($s) !== null));
            $q->whereIn('canonical_status', $statuses);
        }
        if (isset($filter['open']) && $filter['open'] === 'true') {
            $q->whereNull('closed_at');
        }
        if (isset($filter['mine']) && $filter['mine'] === 'true') {
            $q->where('originator_id', $principal->id);
        }
        if (isset($filter['product_key']) && is_string($filter['product_key'])) {
            $q->where('product_key', $filter['product_key']);
        }
        if (isset($filter['party_id']) && is_string($filter['party_id'])) {
            $q->whereIn('id', ApplicantRecord::query()->select('application_id')->where('party_id', $filter['party_id']));
        }
        if (isset($filter['q']) && is_string($filter['q']) && trim($filter['q']) !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($filter['q'])).'%';
            $q->where(static fn ($w) => $w->where('reference', 'ilike', $needle)->orWhere('primary_applicant_name', 'ilike', $needle));
        }

        return CursorPaginator::paginate($q, $request, static fn (ApplicationRecord $r): array => ApplicationPresenter::summary($r));
    }

    /**
     * Counts per canonical status within the caller's scope (pipeline tiles, RPT groundwork).
     *
     * @return array{data: array{by_status: array<string, int>, open: int, total: int}}
     */
    public function stats(Principal $principal): array
    {
        $q = ApplicationRecord::query();
        $this->scope->apply($q, $principal, self::READ, self::scopeColumns());
        $rows = $q->toBase()->select('canonical_status', DB::raw('count(*) as n'))->groupBy('canonical_status')->get();
        $by = [];
        $total = 0;
        $open = 0;
        foreach ($rows as $row) {
            $n = (int) $row->n;
            $by[(string) $row->canonical_status] = $n;
            $total += $n;
            if (! CanonicalStatus::from((string) $row->canonical_status)->isTerminal()) {
                $open += $n;
            }
        }

        return ['data' => ['by_status' => $by, 'open' => $open, 'total' => $total]];
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function show(string $id, Principal $principal): array
    {
        $this->authorized($id, $principal);

        return $this->viewForCommand($id);
    }

    /**
     * Representation after a command (the bus already authorised it).
     *
     * @return array{data: array<string, mixed>, etag: string}
     */
    public function viewForCommand(string $id): array
    {
        $r = ApplicationRecord::query()->findOrFail($id);
        $applicants = array_values(ApplicantRecord::query()->where('application_id', $id)->orderBy('created_at')->get()->all());

        return ['data' => ApplicationPresenter::detail($r, $applicants, $this->completeness($r)), 'etag' => self::etag($r->id, $r->version)];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function timeline(string $id, Principal $principal): array
    {
        $this->authorized($id, $principal);
        $out = [];
        foreach ($this->store->events($id) as $e) {
            $out[] = [
                'version' => $e['version'],
                'type' => $e['type'],
                'occurred_at' => $e['occurred_at']->format('Y-m-d\TH:i:s.u\Z'),
                'actor' => ['type' => $e['actor_type'], 'id' => $e['actor_id']],
                'correlation_id' => $e['correlation_id'],
                'payload' => $e['payload'],
            ];
        }

        return ['data' => $out];
    }

    /**
     * State reconstructed by replaying events up to an instant (FR-AUD-010).
     *
     * @return array{data: array{as_at: string, state: array<string, mixed>|null}}
     */
    public function asAt(string $id, string $instant, Principal $principal): array
    {
        $this->authorized($id, $principal);
        try {
            $at = new DateTimeImmutable($instant);
        } catch (Exception) {
            throw ValidationFailed::with(['t' => 't must be an ISO 8601 date-time.']);
        }
        $app = Application::replay($id, $this->store->events($id), $at);

        return ['data' => ['as_at' => $at->format(DATE_ATOM), 'state' => $app->version() === 0 ? null : $app->snapshot()]];
    }

    /**
     * Completeness against the pinned product (FR-APP-008): missing data now;
     * document checklist status joins in P1-DOC-02.
     *
     * @return array{percent: int, missing: list<string>, checklist: list<array{code: string, name: string, mandatory: bool}>}
     */
    public function completeness(ApplicationRecord $r): array
    {
        $missing = [];
        foreach (['requested_amount' => $r->requested_amount, 'tenor_months' => $r->tenor_months, 'purpose' => $r->purpose] as $field => $value) {
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }
        $product = $this->products->version($r->product_version_id);
        $types = array_values(array_map(strval(...), ApplicantRecord::query()->where('application_id', $r->id)->distinct()->pluck('party_type')->all()));
        $checklist = $product === null ? [] : $product->checklistFor($types, $r->channel, Money::of($r->requested_amount ?? '0', $r->currency));
        $required = 3;

        return ['percent' => (int) intdiv(($required - count($missing)) * 100, $required), 'missing' => $missing, 'checklist' => $checklist];
    }

    private function authorized(string $id, Principal $principal): ApplicationRecord
    {
        $r = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ApplicationRecord::query()->find($id) : null;
        if ($r === null) {
            throw new NotFound('Application not found.');
        }
        $this->gate->authorize($principal, self::READ, self::attributes($r));

        return $r;
    }
}
