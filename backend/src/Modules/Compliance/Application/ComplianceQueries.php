<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ComplianceQueries
{
    public function __construct(
        private readonly ListScopeFilter $scope,
        private readonly AuthorizationGate $gate,
        private readonly PartyDirectory $parties,
        private readonly ApplicationReader $applications,
        private readonly KycEvaluator $kyc,
    ) {}

    /** @return array<string, mixed> */
    public function alerts(Request $request, Principal $principal): array
    {
        $q = ScreeningAlert::query();
        $this->scope->apply($q, $principal, 'screening:review', new ScopeColumns(orgUnit: 'org_unit_id'));
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        if (isset($filter['status']) && is_string($filter['status'])) {
            $q->whereIn('status', explode(',', $filter['status']));
        }
        if (isset($filter['application_id']) && is_string($filter['application_id'])) {
            $q->where('application_id', $filter['application_id']);
        }
        $names = [];

        return CursorPaginator::paginate($q, $request, function (ScreeningAlert $a) use (&$names): array {
            $names[$a->party_id] ??= $this->parties->find($a->party_id)?->displayName;

            return AlertPresenter::present($a, $names[$a->party_id]);
        });
    }

    /** @return array{data: array<string, mixed>} */
    public function alert(string $id, Principal $principal): array
    {
        $a = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ScreeningAlert::query()->find($id) : null;
        if ($a === null) {
            throw new NotFound('Screening alert not found.');
        }
        $this->gate->authorize($principal, 'screening:review', new ResourceAttributes(orgUnitId: $a->org_unit_id, entityType: 'screening_alert', entityId: $a->id));

        return ['data' => AlertPresenter::present($a, $this->parties->find($a->party_id)?->displayName)];
    }

    /**
     * KYC / CDD gate status of an application, with screening evidence (FR-CUS-007).
     *
     * @return array{data: array<string, mixed>}
     */
    public function kycStatus(string $applicationId, Principal $principal): array
    {
        $app = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, entityType: 'application', entityId: $app->id));
        $gate = $this->kyc->evaluate($applicationId) ?? throw new NotFound('Application not found.');
        $runs = [];
        foreach (DB::table('screening_runs')->where('application_id', $applicationId)->orderBy('screened_at')->get() as $r) {
            $runs[] = [
                'id' => (string) $r->id, 'party_id' => (string) $r->party_id, 'subject_name' => (string) $r->subject_name, 'trigger' => (string) $r->trigger,
                'list_version' => (string) $r->list_version, 'provider_reference' => (string) $r->provider_reference, 'hit_count' => (int) $r->hit_count,
                'screened_at' => (new \DateTimeImmutable((string) $r->screened_at))->format('Y-m-d\TH:i:s.u\Z'),
            ];
        }
        $alerts = array_values(array_map(fn (ScreeningAlert $a): array => AlertPresenter::present($a, $this->parties->find($a->party_id)?->displayName), ScreeningAlert::query()->where('application_id', $applicationId)->orderBy('created_at')->get()->all()));

        return ['data' => ['application_id' => $applicationId, 'status' => $app->status->value] + $gate + ['screening_runs' => $runs, 'alerts' => $alerts]];
    }
}
