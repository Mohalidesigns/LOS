<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\LegalEntities;

use Fundly\Modules\Platform\Application\Support\PlatformPresenter;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;

final class CreateLegalEntityHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateLegalEntity);
        if (LegalEntity::query()->where('code', $command->code)->exists()) {
            throw new CodedConflict('legal-entity-code-taken', "Legal entity {$command->code} already exists.");
        }
        $le = new LegalEntity;
        $le->forceFill([
            'code' => $command->code,
            'name' => $command->name,
            'jurisdiction' => $command->jurisdiction,
            'licence_category' => $command->licenceCategory,
            'base_currency' => $command->baseCurrency,
            'timezone' => $command->timezone,
            'org_level_labels' => array_values($command->orgLevelLabels),
            'status' => 'active',
        ])->save();
        $le->refresh();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'legal_entity', entityId: $le->id, after: PlatformPresenter::legalEntity($le)));

        return ['data' => PlatformPresenter::legalEntity($le), 'etag' => PlatformPresenter::etag($le)];
    }
}
