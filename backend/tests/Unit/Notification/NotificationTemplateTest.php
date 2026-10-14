<?php

declare(strict_types=1);

use Fundly\Modules\Notification\Domain\DefaultTemplates;
use Fundly\Modules\Notification\Domain\Format;
use Fundly\Modules\Notification\Domain\NotificationTemplate;
use Fundly\Shared\Exceptions\ValidationFailed;

it('ships a valid default template for every event', function () {
    foreach (DefaultTemplates::all() as $event => $content) {
        NotificationTemplate::validate($content);
        expect($content['event'])->toBe($event);
    }
    expect(array_keys(DefaultTemplates::all()))->toContain('application.received', 'facility.booked', 'approval.awaiting');
})->group('FR-NTF-002');

it('renders placeholders, falls back to English, and blanks unknown values', function () {
    $t = NotificationTemplate::of(['event' => 'offer.issued', 'audience' => 'customer', 'languages' => [
        'en' => ['email' => ['subject' => 'Offer {{ reference }}', 'body' => 'Hi {{ applicant_name }}{{ bank_name }}']],
        'yo' => ['email' => ['subject' => 'Ìfilọ̀ {{ reference }}', 'body' => 'Ẹ n lẹ {{ applicant_name }}']],
    ]]);
    expect($t->render('email', 'yo', ['reference' => 'R1', 'applicant_name' => 'Amaka']))->toBe(['subject' => 'Ìfilọ̀ R1', 'body' => 'Ẹ n lẹ Amaka'])
        ->and($t->render('email', 'ha', ['reference' => 'R1', 'applicant_name' => 'Amaka']))->toBe(['subject' => 'Offer R1', 'body' => 'Hi Amaka'])
        ->and($t->render('in_app', 'en', []))->toBeNull();
})->group('FR-NTF-002');

it('rejects unknown events, languages and channels', function () {
    expect(fn () => NotificationTemplate::validate(['event' => 'loan.vibes', 'audience' => 'customer', 'languages' => ['en' => ['email' => ['subject' => 's', 'body' => 'b']]]]))->toThrow(ValidationFailed::class)
        ->and(fn () => NotificationTemplate::validate(['event' => 'offer.issued', 'audience' => 'customer', 'languages' => ['yo' => ['email' => ['subject' => 's', 'body' => 'b']]]]))->toThrow(ValidationFailed::class);
    expect(Format::money('2955000', 'NGN'))->toBe('NGN 2,955,000.00')->and(Format::maskEmail('amaka@example.ng'))->toBe('a****@example.ng');
});
