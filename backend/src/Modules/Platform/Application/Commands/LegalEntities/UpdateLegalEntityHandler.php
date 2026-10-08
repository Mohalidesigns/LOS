<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\LegalEntities;

use Fundly\Modules\Platform\Application\Support\PlatformPresenter;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ETag;

final class UpdateLegalEntityHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdateLegalEntity);
        $le = LegalEntity::query()->lockForUpdate()->findOrFail($command->legalEntityId);
        ETag::assertHeader($command->ifMatch, PlatformPresenter::etag($le));
        $before = PlatformPresenter::legalEntity($le);

        if (isset($command->changes['org_level_labels']) && is_array($command->changes['org_level_labels'])) {
            $deepest = (int) OrgUnit::query()->where('legal_entity_id', $le->id)->max('depth');
            if (count($command->changes['org_level_labels']) <= $deepest) {
                throw ValidationFailed::with(['org_level_labels' => 'The hierarchy already has '.($deepest + 1).' levels; provide at least that many labels.']);
            }
        }
        foreach (['name', 'timezone', 'org_level_labels', 'status'] as $f) {
            if (array_key_exists($f, $command->changes)) {
                $le->setAttribute($f, $command->changes[$f]);
            }
        }
        $le->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'legal_entity', entityId: $le->id, before: $before, after: PlatformPresenter::legalEntity($le)));

        return ['data' => PlatformPresenter::legalEntity($le), 'etag' => PlatformPresenter::etag($le)];
    }
}
