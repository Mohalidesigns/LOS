<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Sms;

final readonly class SmsMessage
{
    /**
     * @param  string  $to  international digits without "+", e.g. 2348030000000
     * @param  string  $reference  idempotency reference (24–64 characters): a resend with it is not delivered twice
     */
    public function __construct(public string $to, public string $body, public string $reference) {}

    /** @return array{to: string, length: int, reference: string} what the call log may keep: the number masked, never the text */
    public function forLog(): array
    {
        return ['to' => substr($this->to, 0, 6).str_repeat('*', max(0, strlen($this->to) - 9)).substr($this->to, -3), 'length' => mb_strlen($this->body), 'reference' => $this->reference];
    }
}
