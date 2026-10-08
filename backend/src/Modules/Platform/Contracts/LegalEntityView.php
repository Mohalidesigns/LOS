<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

final readonly class LegalEntityView
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $jurisdiction,
        public string $licenceCategory,
        public string $baseCurrency,
        public string $timezone,
    ) {}
}
