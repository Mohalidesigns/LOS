<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Screening\Dto;

final readonly class ScreeningResult
{
    /** @param list<ScreeningHit> $hits */
    public function __construct(
        public string $providerReference,
        public string $listVersion,
        public array $hits,
    ) {}
}
