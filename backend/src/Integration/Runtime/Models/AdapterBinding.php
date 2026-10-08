<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * port → adapter key + version + config (no secrets; a credentials *reference*)
 * + processing location, per tenant and optionally per legal entity (TRD §11).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $legal_entity_id
 * @property string $port
 * @property string $adapter_key
 * @property string $adapter_version
 * @property array<string, mixed> $config
 * @property string $processing_location
 * @property array<string, mixed>|null $fault_script
 * @property string $status
 * @property bool $allow_in_production
 * @property string|null $change_request_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class AdapterBinding extends TenantModel
{
    protected $table = 'adapter_bindings';

    protected function casts(): array
    {
        return ['config' => 'array', 'fault_script' => 'array', 'allow_in_production' => 'boolean'];
    }
}
