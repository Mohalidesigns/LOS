<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $application_id
 * @property string $event
 * @property string $channel
 * @property string $audience
 * @property string|null $recipient_user_id
 * @property string|null $recipient_party_id
 * @property string|null $recipient_hint
 * @property string $template_source
 * @property string|null $template_version_id
 * @property string $language
 * @property string $subject
 * @property string $body
 * @property string|null $link
 * @property list<array{document_id: string, version_id: string, filename: string}> $attachments
 * @property string $status
 * @property int $attempts
 * @property string|null $error
 * @property string $dedupe_key
 * @property Carbon $created_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $read_at
 */
final class Communication extends TenantModel
{
    public $timestamps = false;

    protected $table = 'communications';

    protected function casts(): array
    {
        return ['attachments' => 'array', 'attempts' => 'integer', 'created_at' => 'datetime', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
