<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\WhatsApp;

use Fundly\Integration\Runtime\Errors\NonRetryableError;

/** Outside production a real WhatsApp gateway only messages WHATSAPP_ALWAYS_TO; without it nothing is sent. */
final readonly class NonProductionWhatsAppGuard implements WhatsAppPort
{
    public function __construct(private WhatsAppPort $inner, private ?string $alwaysTo, private string $defaultCountryCode) {}

    public function sendTemplate(WhatsAppMessage $message): WhatsAppReceipt
    {
        if ($this->alwaysTo === null || trim($this->alwaysTo) === '') {
            throw new NonRetryableError('WHATSAPP.CONFIG.ALWAYS_TO_REQUIRED', 'A real WhatsApp gateway outside production sends only to WHATSAPP_ALWAYS_TO (or SMS_ALWAYS_TO); set it to a test handset.');
        }

        return $this->inner->sendTemplate(new WhatsAppMessage(WhatsAppOperations::international($this->alwaysTo, $this->defaultCountryCode), $message->template, $message->body, $message->reference));
    }
}
