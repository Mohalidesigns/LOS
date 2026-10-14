<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\HollaTags;

use Fundly\Integration\Ports\Sms\SmsMessage;
use Fundly\Integration\Ports\Sms\SmsPort;
use Fundly\Integration\Ports\Sms\SmsReceipt;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HollaTags HTTP SMS API (docs.hollatags.com/sms/send): a form POST that
 * answers 200 with a plain-text body, also for errors. `message_uuid` makes
 * a resend with the same reference safe for 30 days, so an unknown outcome
 * can simply be retried. Credentials come from the environment, never from
 * the binding.
 *
 *   sent | <number>,<message id>      accepted
 *   error_gw, error_billing           temporary: retry
 *   error_param, error_limit          the request is wrong: do not retry
 *   error_user, error_credit          account problem: do not retry, fix the account
 */
final class HollaTagsSms implements SmsPort
{
    public const KEY = 'hollatags';

    public const VERSION = '1.0.0';

    /** @param array{url?: mixed, username?: mixed, password?: mixed, sender?: mixed} $credentials */
    public function __construct(private readonly array $credentials) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'cloud:ng', [
            self::OP_SEND => ['support' => 'native', 'idempotency' => 'key'],
        ]);
    }

    public function send(SmsMessage $message): SmsReceipt
    {
        $user = $this->credential('username');
        $pass = $this->credential('password');
        $from = $this->credential('sender');
        $url = is_string($this->credentials['url'] ?? null) && $this->credentials['url'] !== '' ? $this->credentials['url'] : 'https://sms.hollatags.com/api/send';
        try {
            $response = Http::asForm()->timeout(15)->post($url, [
                'user' => $user, 'pass' => $pass, 'from' => $from, 'to' => $message->to, 'msg' => $message->body,
                'type' => preg_match('/[^\x00-\x7F]/', $message->body) === 1 ? 2 : 0,
                'enable_msg_id' => 'true', 'message_uuid' => $message->reference,
            ]);
        } catch (ConnectionException $e) {
            throw new RetryableError('SMS.TRANSPORT.UNREACHABLE', 'HollaTags is unreachable: '.$e->getMessage(), unknownOutcome: true);
        }
        if ($response->serverError()) {
            throw new RetryableError('SMS.PROVIDER.ERROR', 'HollaTags returned HTTP '.$response->status().'.', unknownOutcome: true);
        }
        $body = trim($response->body());

        return match (true) {
            $body === 'sent' => new SmsReceipt(null, self::KEY.'/'.self::VERSION),
            preg_match('/^\d{8,15},([A-Za-z0-9-]+)/', $body, $m) === 1 => new SmsReceipt($m[1], self::KEY.'/'.self::VERSION),
            in_array($body, ['error_gw', 'error_billing'], true) => throw new RetryableError('SMS.PROVIDER.'.strtoupper(substr($body, 6)), "HollaTags: {$body} (temporary)."),
            $body === 'error_user' => throw new NonRetryableError('SMS.ACCOUNT.CREDENTIALS', 'HollaTags rejected the username/password or the account is suspended.'),
            $body === 'error_credit' => throw new NonRetryableError('SMS.ACCOUNT.NO_CREDIT', 'The HollaTags account has no SMS credit.'),
            $body === 'error_limit' => throw new NonRetryableError('SMS.REQUEST.INVALID_NUMBER', 'HollaTags refused the destination number or batch size.'),
            $body === 'error_param' => throw new NonRetryableError('SMS.REQUEST.INVALID', 'HollaTags reported a missing or invalid parameter.'),
            default => throw new RetryableError('SMS.PROVIDER.UNEXPECTED', 'HollaTags returned an unrecognised response.', unknownOutcome: true),
        };
    }

    private function credential(string $key): string
    {
        $v = $this->credentials[$key] ?? null;
        if (! is_string($v) || trim($v) === '') {
            throw new NonRetryableError('SMS.CONFIG.MISSING', "HollaTags {$key} is not configured (SMS_HOLLATAGS_".strtoupper($key).').');
        }

        return $v;
    }
}
