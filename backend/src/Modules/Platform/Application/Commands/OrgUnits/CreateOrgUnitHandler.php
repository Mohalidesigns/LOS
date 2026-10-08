<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\OrgUnits;

use Fundly\Modules\Platform\Application\Support\OrgClosure;
use Fundly\Modules\Platform\Application\Support\PlatformPresenter;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class CreateOrgUnitHandler implements CommandHandler
{
    public function __construct(private readonly OrgClosure $closure) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateOrgUnit);
        $le = LegalEntity::query()->find($command->legalEntityId);
        if (! $le instanceof LegalEntity) {
            throw ValidationFailed::with(['legal_entity_id' => 'Legal entity does not exist.']);
        }
        $depth = 0;
        if ($command->parentId !== null) {
            $parent = OrgUnit::query()->find($command->parentId);
            if (! $parent instanceof OrgUnit || $parent->legal_entity_id !== $le->id) {
                throw ValidationFailed::with(['parent_id' => 'Parent org unit does not exist in this legal entity.']);
            }
            $depth = $parent->depth + 1;
        }
        if ($depth >= count($le->org_level_labels)) {
            throw new DomainRuleViolation('The hierarchy of this legal entity is configured for '.count($le->org_level_labels).' levels; add a level label first.');
        }
        if (OrgUnit::query()->where('legal_entity_id', $le->id)->where('code', $command->code)->exists()) {
            throw new CodedConflict('org-unit-code-taken', "Org unit {$command->code} already exists in this legal entity.");
        }

        $ou = new OrgUnit;
        $ou->forceFill([
            'legal_entity_id' => $le->id,
            'parent_id' => $command->parentId,
            'code' => $command->code,
            'name' => $command->name,
            'depth' => $depth,
            'status' => 'active',
        ])->save();
        $ou->refresh();
        $this->closure->insertNode($ou->id, $command->parentId);

        $presented = PlatformPresenter::orgUnit($ou, PlatformPresenter::levelLabel($le, $depth));
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'org_unit', entityId: $ou->id, after: $presented));

        return ['data' => $presented, 'etag' => PlatformPresenter::etag($ou)];
    }
}
