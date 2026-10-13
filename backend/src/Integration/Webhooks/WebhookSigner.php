<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks;

/**
 * `Fundly-Signature: t=<unix>,v1=<hex HMAC-SHA256(secret, "<t>.<body>")>`
 * (TRD §10.1). The timestamp lets receivers reject replays; the body is
 * signed exactly as sent.
 */
final class WebhookSigner
{
    public static function header(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** Receiver-side check, also used by the tests and the API guide. */
    public static function verify(string $secret, string $header, string $body, int $now, int $toleranceSeconds = 300): bool
    {
        if (preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $m) !== 1 || abs($now - (int) $m[1]) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $m[1].'.'.$body, $secret), $m[2]);
    }
}
