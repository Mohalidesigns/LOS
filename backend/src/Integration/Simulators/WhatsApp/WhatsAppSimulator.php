<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\WhatsApp;

use Fundly\Integration\Ports\WhatsApp\WhatsAppMessage;
use Fundly\Integration\Ports\WhatsApp\WhatsAppPort;
use Fundly\Integration\Ports\WhatsApp\WhatsAppReceipt;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Psr\Log\LoggerInterface;

/**
 * Accepts every template message (logging the masked number and template,
 * never the text). Binding config scripts `unavailable` (retryable),
 * `reject_numbers` (not on WhatsApp) and `timeout` (unknown outcome).
 */
final class WhatsAppSimulator implements WhatsAppPort
{
    public const KEY = 'whatsapp-simulator';

    public const VERSION = '1.0.0';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config, private readonly LoggerInterface $log) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_SEND_TEMPLATE => ['support' => 'native', 'idempotency' => 'none'],
        ]);
    }

    public function sendTemplate(WhatsAppMessage $message): WhatsAppReceipt
    {
        if (($this->config['unavailable'] ?? false) === true) {
            throw new RetryableError('WHATSAPP.PROVIDER.GW', 'Simulated gateway outage (not sent).');
        }
        if (($this->config['timeout'] ?? false) === true) {
            throw new RequiresInterventionError('WHATSAPP.TRANSPORT.UNKNOWN_OUTCOME', 'Simulated timeout after sending.', unknownOutcome: true);
        }
        if (in_array($message->to, (array) ($this->config['reject_numbers'] ?? []), true)) {
            throw new NonRetryableError('WHATSAPP.REQUEST.INVALID_NUMBER', 'Simulated: the number is not on WhatsApp.');
        }
        $this->log->info('WhatsApp simulator accepted a message', $message->forLog());

        return new WhatsAppReceipt('wa-sim-'.substr(hash('sha256', $message->reference), 0, 20), self::KEY.'/'.self::VERSION);
    }
}
