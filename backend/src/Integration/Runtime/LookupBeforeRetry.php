<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Closure;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Errors\RetryableError;

/**
 * Exactly-once effects over an at-least-once channel (register §2.3, FR-CBA-007):
 *
 *  - on a re-delivery (a previous attempt may have reached the provider),
 *    look the effect up by its LOS reference before sending again;
 *  - if a send ends with an unknown outcome (timeout after send), look it up
 *    immediately: found → success; not found → safe to retry with the same key.
 */
final class LookupBeforeRetry
{
    /**
     * @template T
     *
     * @param  Closure(): T  $send
     * @param  Closure(): (T|null)  $lookup
     * @return T
     */
    public static function execute(Closure $send, Closure $lookup, bool $possiblySentBefore): mixed
    {
        if ($possiblySentBefore) {
            $found = $lookup();
            if ($found !== null) {
                return $found;
            }
        }

        try {
            return $send();
        } catch (IntegrationException $e) {
            if (! $e->unknownOutcome) {
                throw $e;
            }
            $found = $lookup();
            if ($found !== null) {
                return $found;
            }
            throw new RetryableError('CBA.TRANSPORT.NOT_APPLIED', 'The provider timed out and the effect was not applied; safe to retry with the same key.', false, $e);
        }
    }
}
