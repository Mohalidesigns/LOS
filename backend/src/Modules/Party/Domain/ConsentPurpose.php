<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

enum ConsentPurpose: string
{
    case DataProcessing = 'data_processing';
    case CreditBureau = 'credit_bureau';
    case CreditReporting = 'credit_reporting';
    case Marketing = 'marketing';
    case ThirdPartySharing = 'third_party_sharing';
}
