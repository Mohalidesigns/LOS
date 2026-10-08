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
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ResourceAttributes;

final class UpdateOrgUnitHandler implements CommandHandler
{
    public function __construct(private readonly OrgClosure $closure, private readonly AuthorizationGate $gate) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdateOrgUnit);
        $ou = OrgUnit::query()->lockForUpdate()->findOrFail($command->orgUnitId);
        ETag::assertHeader($command->ifMatch, PlatformPresenter::etag($ou));
        $le = LegalEntity::query()->findOrFail($ou->legal_entity_id);
        $before = PlatformPresenter::orgUnit($ou, PlatformPresenter::levelLabel($le, $ou->depth));

        if (array_key_exists('parent_id', $command->changes) && $command->changes['parent_id'] !== $ou->parent_id) {
            $newParentId = is_string($command->changes['parent_id']) ? $command->changes['parent_id'] : null;
            $newDepth = 0;
            if ($newParentId !== null) {
                $parent = OrgUnit::query()->find($newParentId);
                if (! $parent instanceof OrgUnit || $parent->legal_entity_id !== $ou->legal_entity_id) {
                    throw ValidationFailed::with(['parent_id' => 'New parent must exist in the same legal entity.']);
                }
                if ($this->closure->isDescendant($newParentId, $ou->id)) {
                    throw new DomainRuleViolation('An org unit cannot be moved under itself or its own descendants.');
                }
                // Moving into a branch also requires authority over the destination.
                $this->gate->authorize($context->principal, $command->permission(), new ResourceAttributes(legalEntityId: $parent->legal_entity_id, orgUnitId: $parent->id, entityType: 'org_unit', entityId: $parent->id));
                $newDepth = $parent->depth + 1;
            }
            if ($newDepth + $this->closure->subtreeHeight($ou->id) >= count($le->org_level_labels)) {
                throw new DomainRuleViolation('The move would exceed the configured hierarchy depth.');
            }
            $ou->parent_id = $newParentId;
            $ou->save();
            $this->closure->move($ou->id, $newParentId);
            $ou->refresh();
        }
        foreach (['name', 'status'] as $f) {
            if (array_key_exists($f, $command->changes)) {
                $ou->setAttribute($f, $command->changes[$f]);
            }
        }
        $ou->save();

        $after = PlatformPresenter::orgUnit($ou, PlatformPresenter::levelLabel($le, $ou->depth));
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'org_unit', entityId: $ou->id, before: $before, after: $after));

        return ['data' => $after, 'etag' => PlatformPresenter::etag($ou)];
    }
}
