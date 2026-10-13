<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use RuntimeException;

/** A saga step could not complete; carries how the instruction should be parked. */
final class StepFailed extends RuntimeException
{
    /** @param 'retryable'|'intervention'|'definite' $kind */
    public function __construct(public readonly string $kind, public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
