<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\WhatsApp;

/**
 * Business-initiated WhatsApp messages (FR-NTF-001, LOS-FR-304). Only
 * pre-approved templates may be sent outside a customer's 24-hour reply
 * window, so a message names its template and carries the filled-in text,
 * which must match the approved wording. HollaTags today; the Meta Cloud
 * API or another BSP can be added behind the same port.
 */
interface WhatsAppPort
{
    public const PORT = 'whatsapp';

    public const OP_SEND_TEMPLATE = 'sendTemplate';

    public function sendTemplate(WhatsAppMessage $message): WhatsAppReceipt;
}
