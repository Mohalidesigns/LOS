<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Domain;

use Fundly\Shared\Exceptions\ProblemException;

/** Licence enforcement refusal (403) with a specific catalogue type. */
final class LicenceProblem extends ProblemException
{
    /** @param array<string, mixed> $extensions */
    public function __construct(private readonly string $slug, string $detail, array $extensions = [])
    {
        parent::__construct($detail, $extensions);
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return 'Licence restriction';
    }
}
