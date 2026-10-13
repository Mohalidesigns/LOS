<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Contracts;

/** A condition still open, as handover needs it (FR-HND-003). */
final readonly class OpenCondition
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $description,
        public bool $mandatory,
        public ?string $ownerId,
        public ?string $dueDate,
    ) {}
}
