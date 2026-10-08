<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Domain;

/** Product categories (FR-PRD-002). */
enum ProductCategory: string
{
    case Personal = 'personal';
    case SalaryBacked = 'salary_backed';
    case AssetFinance = 'asset_finance';
    case Mortgage = 'mortgage';
    case Overdraft = 'overdraft';
    case Revolving = 'revolving';
    case SmeTermLoan = 'sme_term_loan';
    case CorporateTermLoan = 'corporate_term_loan';
    case Agricultural = 'agricultural';
    case DeviceBnpl = 'device_bnpl';

    /** D-038(c): salary-backed products cannot be configured for activation until payroll mandates ship (FR-DSB-010, P4). */
    public function activatableInMvp(): bool
    {
        return $this !== self::SalaryBacked;
    }
}
