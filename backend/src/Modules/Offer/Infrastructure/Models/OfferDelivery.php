<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $offer_id
 * @property string $channel
 * @property string|null $party_id
 * @property string|null $recipient_hint
 * @property string $status
 * @property string|null $failure
 * @property string $requested_by
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 */
final class OfferDelivery extends TenantModel
{
    public $timestamps = false;

    protected $table = 'offer_deliveries';

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
