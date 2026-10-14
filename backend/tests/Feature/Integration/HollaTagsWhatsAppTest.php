<?php

declare(strict_types=1);

use Fundly\Integration\Adapters\HollaTags\HollaTagsWhatsApp;
use Fundly\Integration\Ports\WhatsApp\NonProductionWhatsAppGuard;
use Fundly\Integration\Ports\WhatsApp\WhatsAppMessage;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function hollatagsWhatsApp(): HollaTagsWhatsApp
{
    return new HollaTagsWhatsApp(['url' => 'https://sms.hollatags.test/api/whatsapp/push/', 'username' => 'bank-user', 'password' => 'bank-pass', 'from' => '+234 901 000 0000']);
}

function waTo(): WhatsAppMessage
{
    return new WhatsAppMessage('2348025566677', 'fundly_offer_issued', 'Hello Amaka, your loan offer DEMO-1 is ready.', '01a13943-77d3-7346-b97e-039e6efc30b9');
}

it('pushes an approved template to HollaTags WhatsApp with the documented form', function () {
    Http::fake(['sms.hollatags.test/*' => Http::response('sent', 200)]);
    expect(hollatagsWhatsApp()->sendTemplate(waTo())->engine)->toBe('hollatags-whatsapp/1.0.0');
    Http::assertSent(fn (Request $r): bool => $r->method() === 'POST' && $r->isForm() && $r->url() === 'https://sms.hollatags.test/api/whatsapp/push/'
        && $r['user'] === 'bank-user' && $r['pass'] === 'bank-pass' && $r['whatsapp_from'] === '2349010000000' && $r['whatsapp_to'] === '2348025566677'
        && $r['whatsapp_message'] === 'Hello Amaka, your loan offer DEMO-1 is ready.');
})->group('FR-NTF-001');

it('retries only what was definitely not sent, and never resends an unknown outcome', function (int $status, string $body, string $class, bool $unknown) {
    Http::fake(['sms.hollatags.test/*' => Http::response($body, $status)]);
    try {
        hollatagsWhatsApp()->sendTemplate(waTo());
        $this->fail('expected an error');
    } catch (IntegrationException $e) {
        expect($e)->toBeInstanceOf($class)->and($e->unknownOutcome)->toBe($unknown);
    }
})->with([
    [200, 'error_gw', RetryableError::class, false],
    [200, 'error_user', NonRetryableError::class, false],
    [200, 'error_param', NonRetryableError::class, false],
    [200, 'error_limit', NonRetryableError::class, false],
    [502, '', RequiresInterventionError::class, true],
    [200, 'queued?', RequiresInterventionError::class, true],
]);

it('outside production only messages the configured test handset', function () {
    Http::fake(['sms.hollatags.test/*' => Http::response('sent', 200)]);
    expect(fn () => (new NonProductionWhatsAppGuard(hollatagsWhatsApp(), '', '234'))->sendTemplate(waTo()))->toThrow(NonRetryableError::class, 'WHATSAPP_ALWAYS_TO');
    Http::assertNothingSent();
    (new NonProductionWhatsAppGuard(hollatagsWhatsApp(), '08030001111', '234'))->sendTemplate(waTo());
    Http::assertSent(fn (Request $r): bool => $r['whatsapp_to'] === '2348030001111');
})->group('FR-NTF-001');
