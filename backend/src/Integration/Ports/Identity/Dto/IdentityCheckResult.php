<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Identity\Dto;

/**
 * Outcome of an identity lookup. `matchScore` is a decimal string 0–100;
 * `matched` / `mismatched` name the attributes compared. No raw identity
 * data from the provider is carried back beyond what matching needs.
 */
final readonly class IdentityCheckResult
{
    public const VERIFIED = 'verified';

    public const MISMATCH = 'mismatch';

    public const NOT_FOUND = 'not_found';

    /**
     * @param  list<string>  $matched
     * @param  list<string>  $mismatched
     */
    public function __construct(
        public string $outcome,
        public string $matchScore,
        public string $providerReference,
        public array $matched,
        public array $mismatched,
    ) {}
}
