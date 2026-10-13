<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Contracts;

use DateTimeImmutable;

/** The destination account a passing pre-disbursement check verified (FR-CPR-005). */
final readonly class VerifiedDestination
{
    public function __construct(
        public string $checkId,
        public string $bankCode,
        public string $accountNo,
        public ?string $accountName,
        public DateTimeImmutable $checkedAt,
        public DateTimeImmutable $validUntil,
    ) {}
}
