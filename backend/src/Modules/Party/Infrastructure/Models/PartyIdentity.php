<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $party_id
 * @property string $type
 * @property string $value_enc
 * @property string $value_bidx
 * @property string $value_masked
 * @property string $verification_status
 * @property Carbon|null $verified_at
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property string|null $match_score
 * @property array<string, mixed>|null $verification_details
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PartyIdentity extends TenantModel
{
    protected $table = 'party_identities';

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'verification_details' => 'array'];
    }
}
