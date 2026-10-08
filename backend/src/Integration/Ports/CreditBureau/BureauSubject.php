<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CreditBureau;

final readonly class BureauSubject
{
    public function __construct(
        public string $kind,
        public string $identifier,
        public string $identifierType,
        public string $name,
        public ?string $dateOfBirth,
        public string $consentReference,
    ) {}
}
