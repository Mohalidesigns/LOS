<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Sms;

use Fundly\Integration\Runtime\Errors\NonRetryableError;

/**
 * Wraps a real gateway outside production so test and demo data can never
 * text a real person: every message goes to SMS_ALWAYS_TO, and without it
 * nothing is sent. Simulators are not wrapped.
 */
final readonly class NonProductionSmsGuard implements SmsPort
{
    public function __construct(private SmsPort $inner, private ?string $alwaysTo, private string $defaultCountryCode) {}

    public function send(SmsMessage $message): SmsReceipt
    {
        if ($this->alwaysTo === null || trim($this->alwaysTo) === '') {
            throw new NonRetryableError('SMS.CONFIG.ALWAYS_TO_REQUIRED', 'A real SMS gateway outside production sends only to SMS_ALWAYS_TO; set it to a test handset.');
        }

        return $this->inner->send(new SmsMessage(SmsOperations::international($this->alwaysTo, $this->defaultCountryCode), $message->body, $message->reference));
    }
}
