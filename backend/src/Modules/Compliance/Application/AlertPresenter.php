<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application;

use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;

final class AlertPresenter
{
    /** @return array<string, mixed> */
    public static function present(ScreeningAlert $a, ?string $partyName = null): array
    {
        return [
            'id' => $a->id,
            'application_id' => $a->application_id,
            'party_id' => $a->party_id,
            'party_name' => $partyName,
            'category' => $a->category,
            'list_name' => $a->list_name,
            'list_version' => $a->list_version,
            'entry_id' => $a->entry_id,
            'matched_name' => $a->matched_name,
            'score' => $a->score,
            'status' => $a->status,
            'proposed_decision' => $a->proposed_decision,
            'proposed_reason' => $a->proposed_reason,
            'evidence_ref' => $a->evidence_ref,
            'proposed_by' => $a->proposed_by,
            'proposed_at' => $a->proposed_at?->toIso8601ZuluString('microsecond'),
            'confirmed_by' => $a->confirmed_by,
            'confirmed_at' => $a->confirmed_at?->toIso8601ZuluString('microsecond'),
            'confirmation_note' => $a->confirmation_note,
            'created_at' => $a->created_at->toIso8601ZuluString('microsecond'),
        ];
    }
}
