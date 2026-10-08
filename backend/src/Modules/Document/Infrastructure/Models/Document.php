<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string|null $checklist_item_id
 * @property string|null $party_id
 * @property string $document_type
 * @property string $title
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Document extends TenantModel
{
    protected $table = 'documents';
}
