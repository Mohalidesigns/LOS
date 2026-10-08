<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\OrgUnits;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(CreateOrgUnitHandler::class)]
final readonly class CreateOrgUnit implements Command, ValidatesInput
{
    public function __construct(public string $legalEntityId, public ?string $parentId, public string $code, public string $name)
    {
    }

    public function action(): string
    {
        return 'platform.org_unit.created';
    }

    public function permission(): string
    {
        return 'org_unit:manage';
    }

    /** Scope check against the parent (or the legal entity for a root). */
    public function resource(): ResourceRef
    {
        return $this->parentId !== null ? new ResourceRef('org_unit', $this->parentId) : new ResourceRef('legal_entity', $this->legalEntityId);
    }

    public function data(): array
    {
        return ['legal_entity_id' => $this->legalEntityId, 'parent_id' => $this->parentId, 'code' => $this->code, 'name' => $this->name];
    }

    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'uuid'],
            'parent_id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9_-]{0,31}$/'],
            'name' => ['required', 'string', 'max:200'],
        ];
    }
}
