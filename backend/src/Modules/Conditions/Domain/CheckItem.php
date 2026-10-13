<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Domain;

/** One line of the final pre-disbursement checklist (FR-CPR-003). */
final readonly class CheckItem
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const NOT_APPLICABLE = 'not_applicable';

    public const CODES = [
        'kyc_current' => 'KYC current',
        'screening_clear' => 'Screening clear (re-run now)',
        'documentation_executed' => 'Documentation executed',
        'conditions_precedent' => 'Mandatory conditions precedent met or waived',
        'approval_valid' => 'Approval unexpired',
        'account_verified' => 'Destination account verified',
        'security_perfected' => 'Security perfected',
        'insurance_in_force' => 'Insurance in force',
    ];

    public function __construct(public string $code, public string $status, public string $detail) {}

    public static function pass(string $code, string $detail): self
    {
        return new self($code, self::PASS, $detail);
    }

    public static function fail(string $code, string $detail): self
    {
        return new self($code, self::FAIL, $detail);
    }

    public static function notApplicable(string $code, string $detail): self
    {
        return new self($code, self::NOT_APPLICABLE, $detail);
    }

    /** @return array{code: string, label: string, status: string, detail: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => self::CODES[$this->code] ?? $this->code, 'status' => $this->status, 'detail' => $this->detail];
    }

    /** @param list<self> $items */
    public static function passed(array $items): bool
    {
        foreach ($items as $i) {
            if ($i->status === self::FAIL) {
                return false;
            }
        }

        return true;
    }
}
