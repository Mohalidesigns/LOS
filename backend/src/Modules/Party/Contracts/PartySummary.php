<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts;

final readonly class PartySummary
{
    public function __construct(
        public string $id,
        public string $type,
        public string $displayName,
        public ?string $orgUnitId,
        public string $status,
    ) {}
}
