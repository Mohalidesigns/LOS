<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\LegalEntities;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * A legal entity resolves its jurisdiction pack (jurisdiction × licence
 * category), base currency and timezone (TRD §2.3), and labels the levels of
 * its org hierarchy (FR-TEN-003: configurable depth and labels).
 */
#[HandledBy(CreateLegalEntityHandler::class)]
final readonly class CreateLegalEntity implements Command, ValidatesInput
{
    /** @param list<string> $orgLevelLabels */
    public function __construct(
        public string $code,
        public string $name,
        public string $jurisdiction,
        public string $licenceCategory,
        public string $baseCurrency,
        public string $timezone,
        public array $orgLevelLabels,
    ) {}

    public function action(): string
    {
        return 'platform.legal_entity.created';
    }

    public function permission(): string
    {
        return 'legal_entity:manage';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return [
            'code' => $this->code, 'name' => $this->name, 'jurisdiction' => $this->jurisdiction,
            'licence_category' => $this->licenceCategory, 'base_currency' => $this->baseCurrency,
            'timezone' => $this->timezone, 'org_level_labels' => $this->orgLevelLabels,
        ];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9_-]{1,31}$/'],
            'name' => ['required', 'string', 'max:200'],
            'jurisdiction' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'licence_category' => ['required', 'string', 'in:commercial_bank,merchant_bank'],
            'base_currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'timezone' => ['required', 'timezone:all'],
            'org_level_labels' => ['required', 'array', 'min:1', 'max:10'],
            'org_level_labels.*' => ['string', 'max:64', 'distinct'],
        ];
    }
}
