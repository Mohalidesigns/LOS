<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $party_id
 * @property string $bureau
 * @property string $report_reference
 * @property string $identifier_type
 * @property bool $hit
 * @property array<string, mixed> $profile
 * @property string $consent_reference
 * @property string $pulled_by
 * @property Carbon $pulled_at
 * @property Carbon $valid_until
 */
final class BureauReport extends TenantModel
{
    public $timestamps = false;

    protected $table = 'bureau_reports';

    protected function casts(): array
    {
        return ['hit' => 'boolean', 'profile' => 'array', 'pulled_at' => 'datetime', 'valid_until' => 'datetime'];
    }
}
