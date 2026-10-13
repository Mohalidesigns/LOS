<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $offer_id
 * @property int $position
 * @property string $role
 * @property string|null $party_id
 * @property string $display_name
 * @property string|null $method
 * @property Carbon|null $signed_at
 * @property string|null $witness_name
 * @property string|null $place
 * @property string|null $recorded_by
 * @property Carbon|null $recorded_at
 */
final class OfferSignatory extends TenantModel
{
    public $timestamps = false;

    protected $table = 'offer_signatories';

    protected function casts(): array
    {
        return ['position' => 'integer', 'signed_at' => 'datetime', 'recorded_at' => 'datetime'];
    }
}
