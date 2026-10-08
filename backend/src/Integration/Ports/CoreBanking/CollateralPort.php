<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\CollateralRecord;

/**
 * Collateral sub-port.
 */
interface CollateralPort
{
    /**
     * State-changing. Returns the CBA collateral id.
     */
    public function registerCollateral(CollateralRecord $record, string $idempotencyKey): string;

    /**
     * State-changing.
     */
    public function updateCollateral(string $cbaCollateralId, CollateralRecord $record, string $idempotencyKey): string;

    /**
     * State-changing.
     */
    public function releaseCollateral(string $cbaCollateralId, string $idempotencyKey): string;
}
