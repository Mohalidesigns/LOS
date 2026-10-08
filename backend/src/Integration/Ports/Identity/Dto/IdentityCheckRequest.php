<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Identity\Dto;

/** Canonical identity lookup (BVN / NIN …) with the claimed attributes to match against. */
final readonly class IdentityCheckRequest
{
    public function __construct(
        public string $idType,
        public string $idNumber,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $dateOfBirth,
        public ?string $phone,
    ) {}
}
