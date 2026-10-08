<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $party_id
 * @property string $related_party_id
 * @property string $role
 * @property string|null $ownership_percent
 * @property string|null $notes
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PartyRelationship extends TenantModel
{
    protected $table = 'party_relationships';
}
