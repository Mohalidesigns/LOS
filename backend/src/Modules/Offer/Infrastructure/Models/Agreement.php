<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $offer_id
 * @property string $template_version_id
 * @property string $execution_method
 * @property string $document_id
 * @property string $document_version_id
 * @property string $sha256
 * @property string $offer_document_sha256
 * @property list<array<string, mixed>> $signatures
 * @property string $recorded_by
 * @property Carbon $executed_at
 */
final class Agreement extends TenantModel
{
    public $timestamps = false;

    protected $table = 'agreements';

    protected function casts(): array
    {
        return ['signatures' => 'array', 'executed_at' => 'datetime'];
    }
}
