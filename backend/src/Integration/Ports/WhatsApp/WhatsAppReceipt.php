<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\WhatsApp;

final readonly class WhatsAppReceipt
{
    public function __construct(public ?string $providerMessageId, public string $engine) {}
}
