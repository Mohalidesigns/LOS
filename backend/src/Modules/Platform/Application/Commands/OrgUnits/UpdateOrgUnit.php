<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\OrgUnits;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Rename, change status, or move (re-parent) an org unit with its subtree. */
#[HandledBy(UpdateOrgUnitHandler::class)]
final readonly class UpdateOrgUnit implements Command, ValidatesInput
{
    /** @param array<string, mixed> $changes subset of name, status, parent_id */
    public function __construct(public string $orgUnitId, public array $changes, public ?string $ifMatch)
    {
    }

    public function action(): string
    {
        return 'platform.org_unit.updated';
    }

    public function permission(): string
    {
        return 'org_unit:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('org_unit', $this->orgUnitId);
    }

    public function data(): array
    {
        return $this->changes;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', 'in:active,inactive'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
