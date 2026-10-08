<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\Customer;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerRef;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSearchCriteria;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSummary;

/**
 * Customer sub-port.
 */
interface CustomerPort
{
    /**
     * @return list<CustomerSummary>
     */
    public function searchCustomers(CustomerSearchCriteria $criteria): array;

    public function getCustomer(string $cbaCustomerId): Customer;

    /**
     * State-changing. Lookup-before-retry on losPartyId.
     */
    public function createCustomer(CustomerCreate $customer, string $idempotencyKey): CustomerRef;

    /**
     * State-changing.
     *
     * @param  array<string, string>  $changes
     */
    public function updateCustomer(string $cbaCustomerId, array $changes, string $idempotencyKey): string;

    /**
     * @return list<array<string, string>>
     */
    public function getRelationships(string $cbaCustomerId): array;

    /**
     * Lookup used before re-sending createCustomer (CBI 1.0 lookup-before-retry support).
     */
    public function findCustomerByLosReference(string $losPartyId): ?CustomerSummary;
}
