<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

use DateTimeImmutable;

/** Read model of one configuration version, for modules that pin or consume configuration. */
final readonly class ConfigVersionView
{
    /** @param array<string, mixed> $content */
    public function __construct(
        public string $id,
        public string $artifactId,
        public string $type,
        public string $key,
        public string $name,
        public int $versionNo,
        public string $status,
        public array $content,
        public ?DateTimeImmutable $activatedAt,
    ) {}
}
