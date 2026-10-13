<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $sequence
 * @property string $status
 * @property string $template_key
 * @property string $template_version_id
 * @property int $template_version_no
 * @property string $execution_method
 * @property string $approval_request_id
 * @property array<string, mixed> $terms
 * @property array<string, mixed> $key_facts
 * @property list<array<string, mixed>> $schedule
 * @property string $schedule_source
 * @property string $document_id
 * @property string $document_version_id
 * @property string $document_sha256
 * @property string $issued_by
 * @property Carbon $issued_at
 * @property Carbon $valid_until
 * @property string|null $reissue_of
 * @property int $reissue_count
 * @property string|null $decision_reason_code
 * @property string|null $decision_note
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 */
final class Offer extends TenantModel
{
    public $timestamps = false;

    protected $table = 'offers';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'template_version_no' => 'integer', 'reissue_count' => 'integer', 'terms' => 'array', 'key_facts' => 'array', 'schedule' => 'array', 'issued_at' => 'datetime', 'valid_until' => 'datetime', 'decided_at' => 'datetime'];
    }
}
