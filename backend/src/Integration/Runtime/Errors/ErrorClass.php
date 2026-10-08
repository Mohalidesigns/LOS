<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

/** Canonical error taxonomy (FR-CBA-011, register §2.2). */
enum ErrorClass: string
{
    /** Transient (network, 5xx, timeout before send, lock contention): backoff + jitter, then requires_intervention. */
    case Retryable = 'retryable';
    /** Malformed request or mapping error: exception queue for a configuration fix, no retry. */
    case NonRetryable = 'non_retryable';
    /** Unknown outcome, partial failure or period closed: park; operator chooses a safe action. */
    case RequiresIntervention = 'requires_intervention';
    /** The provider refused the business operation: surface to the business user. */
    case BusinessRejection = 'business_rejection';
}
