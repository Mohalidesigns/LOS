<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Screening\Dto;

final readonly class ScreeningHit
{
    public const CATEGORIES = ['sanction', 'pep', 'adverse_media', 'watchlist'];

    public function __construct(
        public string $listName,
        public string $category,
        public string $entryId,
        public string $matchedName,
        public string $score,
    ) {}
}
