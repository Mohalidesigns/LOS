<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

/** 409 with a specific catalogue code, e.g. idempotency-key-reused, change-request-stale. */
final class CodedConflict extends ProblemException
{
    /** @param array<string, mixed> $extensions */
    public function __construct(private readonly string $slug, string $detail, array $extensions = [])
    {
        parent::__construct($detail, $extensions);
    }

    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        return 'Conflict';
    }
}
