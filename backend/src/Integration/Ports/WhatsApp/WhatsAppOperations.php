<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\WhatsApp;

use Fundly\Integration\Runtime\OperationPolicy;

final class WhatsAppOperations
{
    /** State-changing and not idempotent at HollaTags: never retried inline; an unknown outcome is not resent. */
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: true, timeoutMs: 15000, inlineRetries: 0);
    }

    public static function international(string $phone, string $defaultCountryCode): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $defaultCountryCode.substr($digits, 1);
        }

        return $digits;
    }
}
