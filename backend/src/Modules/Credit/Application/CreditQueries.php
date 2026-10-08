<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Credit\Infrastructure\Models\CreditMemo;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Credit\Infrastructure\Models\PolicyException;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

final class CreditQueries
{
    public function __construct(
        private readonly CreditFileLoader $file,
        private readonly AuthorizationGate $gate,
        private readonly PartyDirectory $parties,
        private readonly MemoComposer $composer,
    ) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function bureauReports(string $applicationId, Principal $principal): array
    {
        $app = $this->authorized($applicationId, $principal);
        $now = $this->file->now();
        $out = [];
        foreach (BureauReport::query()->where('application_id', $app->id)->orderByDesc('pulled_at')->get() as $r) {
            $out[] = CreditPresenter::bureau($r, $this->parties->find($r->party_id)?->displayName, $now);
        }

        return ['data' => $out];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function decisions(string $applicationId, Principal $principal): array
    {
        $app = $this->authorized($applicationId, $principal);
        $rows = DecisionSnapshot::query()->where('application_id', $app->id)->orderByDesc('sequence')->get();
        $exceptions = PolicyException::query()->where('application_id', $app->id)->orderBy('raised_at')->get()->groupBy('decision_id');
        $out = [];
        foreach ($rows as $i => $d) {
            $out[] = CreditPresenter::decision($d, array_values(($exceptions[$d->id] ?? collect())->all()), $i === 0);
        }

        return ['data' => $out];
    }

    /** @return array{data: array<string, mixed>} */
    public function decision(string $id, Principal $principal): array
    {
        $d = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? DecisionSnapshot::query()->find($id) : null;
        if ($d === null) {
            throw new NotFound('Decision not found.');
        }
        $this->authorized($d->application_id, $principal);

        return ['data' => CreditPresenter::decision($d, array_values(PolicyException::query()->where('decision_id', $d->id)->orderBy('raised_at')->get()->all()), $this->file->latestDecision($d->application_id)?->id === $d->id)];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function exceptions(string $applicationId, Principal $principal): array
    {
        $app = $this->authorized($applicationId, $principal);

        return ['data' => array_values(array_map(CreditPresenter::exception(...), PolicyException::query()->where('application_id', $app->id)->orderBy('raised_at')->get()->all()))];
    }

    /** @return array{data: array{latest: array<string, mixed>|null, versions: list<array<string, mixed>>, draft_sections: list<array<string, mixed>>}} */
    public function memo(string $applicationId, Principal $principal): array
    {
        $app = $this->authorized($applicationId, $principal);
        $currency = $app->requestedAmount->currency->code ?? 'NGN';
        $versions = array_values(array_map(static fn (CreditMemo $m): array => CreditPresenter::memo($m, $currency), CreditMemo::query()->where('application_id', $app->id)->orderByDesc('version_no')->get()->all()));

        return ['data' => ['latest' => $versions[0] ?? null, 'versions' => $versions, 'draft_sections' => $this->composer->sections($app, $this->file->latestDecision($app->id))]];
    }

    private function authorized(string $applicationId, Principal $principal): ApplicationSummary
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id));

        return $app;
    }
}
