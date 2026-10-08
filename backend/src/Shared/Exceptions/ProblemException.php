<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every exception that renders as an RFC 9457 problem document.
 * `type` is a stable slug from the problem catalogue; the renderer turns it
 * into a URI (config fundly.api.problem_type_base).
 */
abstract class ProblemException extends RuntimeException
{
    /** @param array<string, mixed> $extensions */
    public function __construct(
        string $detail,
        private readonly array $extensions = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail, 0, $previous);
    }

    abstract public function status(): int;

    abstract public function type(): string;

    abstract public function title(): string;

    /** Machine-readable code; defaults to the type slug. */
    public function code(): string
    {
        return $this->type();
    }

    /** @return array<string, mixed> */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return [];
    }
}
