<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\HollaTags;

use Fundly\Integration\Ports\WhatsApp\WhatsAppMessage;
use Fundly\Integration\Ports\WhatsApp\WhatsAppPort;
use Fundly\Integration\Ports\WhatsApp\WhatsAppReceipt;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HollaTags WhatsApp Push (docs.hollatags.com/whatsapp/whatsapp-push): a
 * form POST of user, pass, whatsapp_from (the approved business number),
 * whatsapp_to and whatsapp_message, whose text must match a template
 * HollaTags has had approved. The reply is plain text, `sent` on success.
 *
 * The push API offers no idempotency reference, so anything that may have
 * been sent (timeout, 5xx, an unrecognised reply) is an unknown outcome and
 * is not resent automatically; definite refusals use the SMS API's codes.
 */
final class HollaTagsWhatsApp implements WhatsAppPort
{
    public const KEY = 'hollatags-whatsapp';

    public const VERSION = '1.0.0';

    /** @param array{url?: mixed, username?: mixed, password?: mixed, from?: mixed} $credentials */
    public function __construct(private readonly array $credentials) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'cloud:ng', [
            self::OP_SEND_TEMPLATE => ['support' => 'native', 'idempotency' => 'none'],
        ]);
    }

    public function sendTemplate(WhatsAppMessage $message): WhatsAppReceipt
    {
        $user = $this->credential('username');
        $pass = $this->credential('password');
        $from = (string) preg_replace('/\D+/', '', $this->credential('from'));
        $url = is_string($this->credentials['url'] ?? null) && $this->credentials['url'] !== '' ? $this->credentials['url'] : 'https://sms.hollatags.com/api/whatsapp/push/';
        try {
            $response = Http::asForm()->timeout(15)->post($url, [
                'user' => $user, 'pass' => $pass, 'whatsapp_from' => $from, 'whatsapp_to' => $message->to, 'whatsapp_message' => $message->body,
            ]);
        } catch (ConnectionException $e) {
            throw new RequiresInterventionError('WHATSAPP.TRANSPORT.UNKNOWN_OUTCOME', 'HollaTags WhatsApp did not answer; the message may have been sent, so it is not resent: '.$e->getMessage(), unknownOutcome: true);
        }
        $body = strtolower(trim($response->body()));
        if ($response->serverError()) {
            throw new RequiresInterventionError('WHATSAPP.PROVIDER.UNKNOWN_OUTCOME', 'HollaTags WhatsApp returned HTTP '.$response->status().'; not resent to avoid a duplicate.', unknownOutcome: true);
        }

        return match (true) {
            $body === 'sent' => new WhatsAppReceipt(null, self::KEY.'/'.self::VERSION),
            in_array($body, ['error_gw', 'error_billing'], true) => throw new RetryableError('WHATSAPP.PROVIDER.'.strtoupper(substr($body, 6)), "HollaTags WhatsApp: {$body} (temporary, not sent)."),
            $body === 'error_user' => throw new NonRetryableError('WHATSAPP.ACCOUNT.CREDENTIALS', 'HollaTags rejected the username/password or the account is suspended.'),
            $body === 'error_credit' => throw new NonRetryableError('WHATSAPP.ACCOUNT.NO_CREDIT', 'The HollaTags account has no credit.'),
            $body === 'error_limit' => throw new NonRetryableError('WHATSAPP.REQUEST.INVALID_NUMBER', 'HollaTags refused the destination number.'),
            $body === 'error_param' => throw new NonRetryableError('WHATSAPP.REQUEST.INVALID', 'HollaTags reported a missing or invalid parameter (is the text an approved template?).'),
            default => throw new RequiresInterventionError('WHATSAPP.PROVIDER.UNKNOWN_OUTCOME', 'HollaTags WhatsApp returned an unrecognised response; not resent.', unknownOutcome: true),
        };
    }

    private function credential(string $key): string
    {
        $v = $this->credentials[$key] ?? null;
        if (! is_string($v) || trim($v) === '') {
            throw new NonRetryableError('WHATSAPP.CONFIG.MISSING', "HollaTags WhatsApp {$key} is not configured.");
        }

        return $v;
    }
}
