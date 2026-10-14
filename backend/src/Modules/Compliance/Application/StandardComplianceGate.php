<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application;

use Fundly\Modules\Compliance\Application\Commands\ScreenApplication;
use Fundly\Modules\Compliance\Application\Packs\SingleObligorEvaluator;
use Fundly\Modules\Compliance\Contracts\ComplianceGate;
use Fundly\Modules\Compliance\Contracts\KycStatus;
use Fundly\Modules\Compliance\Contracts\RegulatoryLimitCheck;
use Fundly\Modules\Compliance\Contracts\ScreeningOutcome;
use Fundly\Modules\Compliance\Domain\AlertStatus;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;

final class StandardComplianceGate implements ComplianceGate
{
    public function __construct(private readonly KycEvaluator $kyc, private readonly CommandBus $bus, private readonly SingleObligorEvaluator $singleObligor) {}

    public function kyc(string $applicationId): ?KycStatus
    {
        $gate = $this->kyc->evaluate($applicationId);
        if ($gate === null) {
            return null;
        }
        $unmet = array_values(array_map(static fn (array $c): string => $c['detail'], array_filter($gate['conditions'], static fn (array $c): bool => ! $c['met'])));

        return new KycStatus($gate['outcome'], $gate['risk'], $unmet);
    }

    public function rescreen(string $applicationId, string $tenantId): ScreeningOutcome
    {
        /** @var array{runs: int, hits: int} $result */
        $result = $this->bus->dispatch(new ScreenApplication($applicationId, 'pre_disbursement'), Principal::system($tenantId, SystemIdentity::Workflow));
        $open = ScreeningAlert::query()->where('application_id', $applicationId)
            ->whereIn('status', [AlertStatus::Open->value, AlertStatus::PendingConfirmation->value, AlertStatus::ConfirmedMatch->value])->count();

        return new ScreeningOutcome($result['runs'], $result['hits'], $open);
    }

    public function regulatoryLimits(string $applicationId, string $stage, string $actorId): RegulatoryLimitCheck
    {
        return $this->singleObligor->evaluate($applicationId, $stage, $actorId);
    }
}
