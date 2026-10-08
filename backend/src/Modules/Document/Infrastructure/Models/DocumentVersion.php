<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property int $version_no
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_status
 * @property string|null $scan_signature
 * @property string $scanner
 * @property string|null $storage_key
 * @property int|null $key_version
 * @property string $uploaded_by
 * @property Carbon $uploaded_at
 */
final class DocumentVersion extends TenantModel
{
    public $timestamps = false;

    protected $table = 'document_versions';

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'size_bytes' => 'integer', 'key_version' => 'integer', 'uploaded_at' => 'datetime'];
    }
}
