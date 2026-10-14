<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\WhatsApp;

final readonly class WhatsAppMessage
{
    /**
     * @param  string  $to  international digits without "+", e.g. 2348030000000
     * @param  string  $template  the approved template's name
     * @param  string  $body  the template text with its variables filled in
     * @param  string  $reference  our reference for the message (logs, provider idempotency where offered)
     */
    public function __construct(public string $to, public string $template, public string $body, public string $reference) {}

    /** @return array{to: string, template: string, length: int, reference: string} the number masked, never the text */
    public function forLog(): array
    {
        return ['to' => substr($this->to, 0, 6).str_repeat('*', max(0, strlen($this->to) - 9)).substr($this->to, -3), 'template' => $this->template, 'length' => mb_strlen($this->body), 'reference' => $this->reference];
    }
}
