<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\Sms;

use Fundly\Integration\Ports\Sms\SmsMessage;
use Fundly\Integration\Ports\Sms\SmsPort;
use Fundly\Integration\Ports\Sms\SmsReceipt;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Psr\Log\LoggerInterface;

/**
 * Accepts every message (logging the masked number, never the text) and
 * returns a deterministic message id per reference. Binding config can
 * script failures: `reject_numbers` (not retryable) and `unavailable`
 * (retryable) for tests.
 */
final class SmsSimulator implements SmsPort
{
    public const KEY = 'sms-simulator';

    public const VERSION = '1.0.0';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config, private readonly LoggerInterface $log) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_SEND => ['support' => 'native', 'idempotency' => 'key'],
        ]);
    }

    public function send(SmsMessage $message): SmsReceipt
    {
        if (($this->config['unavailable'] ?? false) === true) {
            throw new RetryableError('SMS.PROVIDER.ERROR', 'Simulated gateway outage.');
        }
        if (in_array($message->to, (array) ($this->config['reject_numbers'] ?? []), true)) {
            throw new NonRetryableError('SMS.REQUEST.INVALID_NUMBER', 'Simulated rejection of the destination number.');
        }
        $this->log->info('SMS simulator accepted a message', $message->forLog());

        return new SmsReceipt('sim-'.substr(hash('sha256', $message->reference), 0, 24), self::KEY.'/'.self::VERSION);
    }
}
