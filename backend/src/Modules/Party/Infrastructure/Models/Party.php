<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string $display_name
 * @property string $name_normalised
 * @property string|null $org_unit_id
 * @property string $status
 * @property string|null $cba_customer_id
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $gender
 * @property string|null $date_of_birth_enc
 * @property string|null $nationality
 * @property string|null $registration_number
 * @property Carbon|null $incorporation_date
 * @property string|null $sector
 * @property string|null $phone_enc
 * @property string|null $phone_bidx
 * @property string|null $email_enc
 * @property string|null $email_bidx
 * @property string|null $address_enc
 * @property string|null $tin_enc
 * @property string|null $tin_bidx
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Party extends TenantModel
{
    protected $table = 'parties';

    protected function casts(): array
    {
        return ['incorporation_date' => 'date'];
    }
}
