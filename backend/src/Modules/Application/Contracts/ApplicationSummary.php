<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts;

use Fundly\Shared\Money\Money;

/** What other modules may know about an application. */
final readonly class ApplicationSummary
{
    /** @param array<string, string> $applicants party id → role */
    public function __construct(
        public string $id,
        public string $reference,
        public CanonicalStatus $status,
        public string $legalEntityId,
        public string $orgUnitId,
        public string $productId,
        public string $productVersionId,
        public string $segment,
        public string $channel,
        public string $originatorId,
        public ?Money $requestedAmount,
        public ?int $tenorMonths,
        public array $applicants,
        public int $version,
    ) {}
}
