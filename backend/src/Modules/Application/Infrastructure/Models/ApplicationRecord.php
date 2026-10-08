<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * Projection row of an application (rebuildable from application_events).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property string $legal_entity_id
 * @property string $org_unit_id
 * @property string $product_id
 * @property string $product_key
 * @property string $product_version_id
 * @property string $product_name
 * @property string $segment
 * @property string $channel
 * @property string $originator_id
 * @property string $primary_party_id
 * @property string $primary_applicant_name
 * @property string|null $requested_amount
 * @property string $currency
 * @property int|null $tenor_months
 * @property string|null $purpose
 * @property string|null $repayment_frequency
 * @property array<string, mixed> $data
 * @property string $canonical_status
 * @property string|null $resume_to
 * @property string|null $return_to
 * @property int $version
 * @property Carbon|null $submitted_at
 * @property Carbon $status_changed_at
 * @property Carbon|null $closed_at
 * @property string|null $close_reason_code
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ApplicationRecord extends TenantModel
{
    protected $table = 'applications';

    protected function casts(): array
    {
        return [
            'data' => 'array', 'version' => 'integer', 'tenor_months' => 'integer',
            'submitted_at' => 'datetime', 'status_changed_at' => 'datetime', 'closed_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }
}
