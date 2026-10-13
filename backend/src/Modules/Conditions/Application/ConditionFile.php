<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Modules\Conditions\Infrastructure\Models\PreDisbursementCheck;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/** Shared lookups and the readiness rule for condition commands and queries. */
final class ConditionFile
{
    /** Statuses in which conditions are worked: after acceptance, until booking (CS continue after). */
    public const WORKING = [CanonicalStatus::Accepted, CanonicalStatus::ConditionsPrecedent, CanonicalStatus::ReadyForDisbursement, CanonicalStatus::Disbursing];

    public function __construct(private readonly ApplicationReader $applications, private readonly ApplicationLifecycle $lifecycle, private readonly Clock $clock) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function condition(string $id): Condition
    {
        $c = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Condition::query()->find($id) : null;

        return $c ?? throw new NotFound('Condition not found.');
    }

    public function assertWorkable(ApplicationSummary $app, string $kind): void
    {
        $ok = in_array($app->status, self::WORKING, true) || ($kind === 'subsequent' && $app->status === CanonicalStatus::Booked);
        if (! $ok) {
            throw new DomainRuleViolation("Conditions are worked between acceptance and booking; this application is {$app->status->value}.");
        }
    }

    /** @return list<Condition> mandatory precedents still open or awaiting a waiver decision */
    public function outstanding(string $applicationId): array
    {
        return array_values(Condition::query()->where('application_id', $applicationId)->where('kind', 'precedent')->where('mandatory', true)
            ->whereIn('status', [ConditionStatus::Open->value, ConditionStatus::WaiverPending->value])->orderBy('created_at')->get()->all());
    }

    public function latestCheck(string $applicationId): ?PreDisbursementCheck
    {
        return PreDisbursementCheck::query()->where('application_id', $applicationId)->orderByDesc('sequence')->first();
    }

    /** ConditionsPrecedent → ReadyForDisbursement once every mandatory CP is met or waived (TRD §6.2). */
    public function advanceIfReady(string $applicationId, Principal $actor): bool
    {
        $app = $this->application($applicationId);
        if ($app->status !== CanonicalStatus::ConditionsPrecedent || $this->outstanding($applicationId) !== []) {
            return false;
        }
        $this->lifecycle->advance($applicationId, CanonicalStatus::ReadyForDisbursement, 'CONDITIONS_PRECEDENT_MET', 'Every mandatory condition precedent is met or waived.', $actor);

        return true;
    }

    public function resource(ApplicationSummary $app, string $type, string $id): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: $type, entityId: $id);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }
}
