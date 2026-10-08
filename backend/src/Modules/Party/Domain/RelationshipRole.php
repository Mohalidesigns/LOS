<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

enum RelationshipRole: string
{
    case Director = 'director';
    case Shareholder = 'shareholder';
    case BeneficialOwner = 'beneficial_owner';
    case Signatory = 'signatory';
    case CompanySecretary = 'company_secretary';

    public function carriesOwnership(): bool
    {
        return $this === self::Shareholder || $this === self::BeneficialOwner;
    }
}
