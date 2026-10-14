<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Sms;

/**
 * Customer text messages (FR-NTF-001, integration register I-41). HollaTags
 * for Nigerian operators; a simulator in development and tests. Adapters
 * receive the number in international digits (2348030000000) and must make
 * a resend with the same reference safe.
 */
interface SmsPort
{
    public const PORT = 'sms';

    public const OP_SEND = 'sendSms';

    public function send(SmsMessage $message): SmsReceipt;
}
