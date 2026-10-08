<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Screening\Dto;

final readonly class ScreeningSubject
{
    public function __construct(
        public string $reference,
        public string $kind,
        public string $name,
        public ?string $dateOfBirth,
        public ?string $nationality,
        public ?string $registrationNumber,
    ) {}
}
