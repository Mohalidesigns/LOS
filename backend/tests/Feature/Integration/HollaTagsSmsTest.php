<?php

declare(strict_types=1);

use Fundly\Integration\Adapters\HollaTags\HollaTagsSms;
use Fundly\Integration\Ports\Sms\NonProductionSmsGuard;
use Fundly\Integration\Ports\Sms\SmsMessage;
use Fundly\Integration\Ports\Sms\SmsOperations;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function hollatags(): HollaTagsSms
{
    return new HollaTagsSms(['url' => 'https://sms.hollatags.test/api/send', 'username' => 'bank-user', 'password' => 'bank-pass', 'sender' => 'FUNDLY']);
}

function smsTo(string $to = '2348025566677'): SmsMessage
{
    return new SmsMessage($to, 'Your loan offer DEMO-1 is ready.', '01a13943-77d3-7346-b97e-039e6efc30b9');
}

it('posts the documented form to HollaTags with the idempotency reference and reads the message id', function () {
    Http::fake(['sms.hollatags.test/*' => Http::sequence()->push('2348025566677,24137cf8-eccc-469c-80af-109538857e53')->push('sent')]);
    $receipt = hollatags()->send(smsTo());
    expect($receipt->providerMessageId)->toBe('24137cf8-eccc-469c-80af-109538857e53')->and($receipt->engine)->toBe('hollatags/1.0.0');
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->isForm()
        && $r['user'] === 'bank-user' && $r['pass'] === 'bank-pass' && $r['from'] === 'FUNDLY' && $r['to'] === '2348025566677'
        && $r['msg'] === 'Your loan offer DEMO-1 is ready.' && $r['message_uuid'] === '01a13943-77d3-7346-b97e-039e6efc30b9' && $r['enable_msg_id'] === 'true' && (int) $r['type'] === 0);

    expect(hollatags()->send(smsTo())->providerMessageId)->toBeNull();
})->group('FR-NTF-001');

it('maps HollaTags plain-text errors to retryable or definite failures', function (string $body, string $class, string $code) {
    Http::fake(['sms.hollatags.test/*' => Http::response($body, 200)]);
    try {
        hollatags()->send(smsTo());
        $this->fail('expected an error');
    } catch (RetryableError|NonRetryableError $e) {
        expect($e)->toBeInstanceOf($class)->and($e->canonicalCode)->toBe($code);
    }
})->with([
    ['error_gw', RetryableError::class, 'SMS.PROVIDER.GW'],
    ['error_billing', RetryableError::class, 'SMS.PROVIDER.BILLING'],
    ['error_user', NonRetryableError::class, 'SMS.ACCOUNT.CREDENTIALS'],
    ['error_credit', NonRetryableError::class, 'SMS.ACCOUNT.NO_CREDIT'],
    ['error_limit', NonRetryableError::class, 'SMS.REQUEST.INVALID_NUMBER'],
    ['error_param', NonRetryableError::class, 'SMS.REQUEST.INVALID'],
    ['<html>maintenance</html>', RetryableError::class, 'SMS.PROVIDER.UNEXPECTED'],
]);

it('refuses to send without credentials, and outside production only to SMS_ALWAYS_TO', function () {
    Http::fake(['sms.hollatags.test/*' => Http::response('sent', 200)]);
    expect(fn () => (new HollaTagsSms(['username' => 'u', 'password' => '', 'sender' => 'S']))->send(smsTo()))->toThrow(NonRetryableError::class);

    expect(fn () => (new NonProductionSmsGuard(hollatags(), null, '234'))->send(smsTo()))->toThrow(NonRetryableError::class, 'SMS_ALWAYS_TO');
    Http::assertNothingSent();
    (new NonProductionSmsGuard(hollatags(), '0803 000 1111', '234'))->send(smsTo());
    Http::assertSent(fn (Request $r): bool => $r['to'] === '2348030001111');
    expect(SmsOperations::international('+234 802 556 6677', '234'))->toBe('2348025566677')->and(SmsOperations::international('08025566677', '234'))->toBe('2348025566677');
})->group('FR-NTF-001');
