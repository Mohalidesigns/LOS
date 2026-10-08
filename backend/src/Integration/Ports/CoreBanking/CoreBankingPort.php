<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Runtime\CapabilityManifest;

/**
 * Canonical core banking interface, CBI v1.0 (FR-CBA-001, integration register §2).
 * Composed of sub-ports; adapters implement them and declare per-operation
 * support in their capability manifest. No module calls an adapter directly:
 * every call goes through the IntegrationGateway (logging, breaker, retries).
 *
 * Evolution: additive changes are minor versions; removals or semantic changes
 * need a new major version, supported in parallel for at least 12 months.
 */
interface CoreBankingPort
{
    public const CONTRACT_VERSION = '1.0';

    public const PORT = 'core_banking';

    public function customers(): CustomerPort;

    public function accounts(): AccountsPort;

    public function exposure(): ExposurePort;

    public function loanAccounts(): LoanAccountPort;

    public function postings(): PostingsPort;

    public function schedules(): SchedulePort;

    public function collateral(): CollateralPort;

    public function mandates(): MandatesPort;

    public function reference(): ReferenceDataPort;

    public function reconciliation(): ReconciliationPort;

    public function manifest(): CapabilityManifest;
}
