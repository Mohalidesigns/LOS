<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\LegalEntities;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(UpdateLegalEntityHandler::class)]
final readonly class UpdateLegalEntity implements Command, ValidatesInput
{
    /** @param array<string, mixed> $changes subset of name, timezone, org_level_labels, status */
    public function __construct(public string $legalEntityId, public array $changes, public ?string $ifMatch) {}

    public function action(): string
    {
        return 'platform.legal_entity.updated';
    }

    public function permission(): string
    {
        return 'legal_entity:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('legal_entity', $this->legalEntityId);
    }

    public function data(): array
    {
        return $this->changes;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'timezone' => ['sometimes', 'timezone:all'],
            'org_level_labels' => ['sometimes', 'array', 'min:1', 'max:10'],
            'org_level_labels.*' => ['string', 'max:64', 'distinct'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
