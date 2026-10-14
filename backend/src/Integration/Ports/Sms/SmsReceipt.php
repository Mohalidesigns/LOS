<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Sms;

final readonly class SmsReceipt
{
    public function __construct(public ?string $providerMessageId, public string $engine) {}
}
