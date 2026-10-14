<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Contracts;

use DateTimeImmutable;

/** What other modules may ask about the security behind an application. */
interface CollateralRegister
{
    /** Cover and LTV against the product's thresholds; null when nothing is allocated. */
    public function coverage(string $applicationId): ?CollateralCoverage;

    /** Perfection and insurance against disbursement (FR-COL-008). */
    public function security(string $applicationId): SecurityPosition;

    /**
     * Items securing the facility, with what is still open: they follow the loan into servicing (FR-HND-003).
     *
     * @return list<SecuredItem>
     */
    public function securedItems(string $applicationId): array;

    /**
     * Valuations and insurance policies lapsing on or before the date (FR-COL-003/006 prompts).
     *
     * @return list<CollateralAttention>
     */
    public function attentionDue(DateTimeImmutable $until): array;
}
