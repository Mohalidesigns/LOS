<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

/** Applicant types in the MVP (FR-CUS-001; sole proprietor, partnership and group follow in P3). */
enum PartyType: string
{
    case Individual = 'individual';
    case LimitedCompany = 'limited_company';
}
