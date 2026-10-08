<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

use Fundly\Shared\Exceptions\ProblemException;
use InvalidArgumentException;
use Throwable;

/**
 * Base for every error crossing a port boundary. Carries the canonical class
 * and a canonical code of the form <PORT>.<DOMAIN>.<REASON>, e.g.
 * CBA.POSTING.PERIOD_CLOSED. Adapters never leak vendor codes past this.
 */
abstract class IntegrationException extends ProblemException
{
    public const CODE_PATTERN = '/^[A-Z][A-Z0-9]*\.[A-Z][A-Z0-9_]*\.[A-Z][A-Z0-9_]*$/';

    public function __construct(
        public readonly string $canonicalCode,
        string $detail,
        public readonly bool $unknownOutcome = false,
        ?Throwable $previous = null,
    ) {
        if (preg_match(self::CODE_PATTERN, $canonicalCode) !== 1) {
            throw new InvalidArgumentException("Invalid canonical error code {$canonicalCode}.");
        }
        parent::__construct($detail, ['error_class' => $this->errorClass()->value], $previous);
    }

    abstract public function errorClass(): ErrorClass;

    public function code(): string
    {
        return $this->canonicalCode;
    }

    public function type(): string
    {
        return 'integration-'.str_replace('_', '-', $this->errorClass()->value);
    }

    public function title(): string
    {
        return 'Integration error';
    }

    public function status(): int
    {
        return match ($this->errorClass()) {
            ErrorClass::Retryable => 503,
            ErrorClass::BusinessRejection => 422,
            default => 502,
        };
    }
}
