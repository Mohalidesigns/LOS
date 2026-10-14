<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Sms;

use Fundly\Integration\Runtime\OperationPolicy;

final class SmsOperations
{
    /** State-changing: no inline retry; the outbox retries with backoff and the reference keeps it single. */
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: true, timeoutMs: 15000, inlineRetries: 0);
    }

    /** Nigerian 0-prefixed or +-prefixed numbers to international digits. */
    public static function international(string $phone, string $defaultCountryCode): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $defaultCountryCode.substr($digits, 1);
        }

        return $digits;
    }
}
