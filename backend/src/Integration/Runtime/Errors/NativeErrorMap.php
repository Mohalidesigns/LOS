<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

/**
 * Per-adapter mapping from native codes to the canonical taxonomy. An
 * unmapped native code becomes requires_intervention, never success
 * (register §2.2).
 */
final readonly class NativeErrorMap
{
    /** @param array<string, array{0: ErrorClass, 1: string}> $map native code => [class, canonical code] */
    public function __construct(private string $port, private array $map)
    {
    }

    public function toException(string $nativeCode, string $message, bool $afterSend = false): IntegrationException
    {
        $entry = $this->map[$nativeCode] ?? null;
        if ($entry === null) {
            $reason = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $nativeCode) ?? 'UNKNOWN');

            return new RequiresInterventionError($this->port.'.UNMAPPED.'.trim($reason, '_'), "Unmapped provider error {$nativeCode}: {$message}", true);
        }
        [$class, $code] = $entry;

        return match ($class) {
            ErrorClass::Retryable => new RetryableError($code, $message, $afterSend),
            ErrorClass::NonRetryable => new NonRetryableError($code, $message),
            ErrorClass::RequiresIntervention => new RequiresInterventionError($code, $message, $afterSend),
            ErrorClass::BusinessRejection => new BusinessRejectionError($code, $message),
        };
    }
}
