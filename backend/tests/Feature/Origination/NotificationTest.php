<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Notification\Application\Commands\Notify;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->j = Journey::setUp($this);
    $this->compliance = $this->userWith([Permission::ApplicationView, Permission::ScreeningReview]);
});

/** @return list<Email> messages the array mailer captured */
function sentMail(): array
{
    return array_values(array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages())));
}

function comms(string $applicationId): Collection
{
    return DB::table('communications')->where('application_id', $applicationId)->orderBy('created_at')->get();
}

it('notifies customers by email and staff in-app along the journey, and logs every message (FR-NTF-001/005)', function () {
    $app = $this->j->readyForDisbursement();
    $booked = $this->j->book($app);
    LendingFixtures::drainOutbox($this); // emails

    $log = comms($app['id']);
    $events = $log->map(fn ($c) => "{$c->event}:{$c->channel}")->unique()->values()->all();
    expect($events)->toContain('application.received:email', 'approval.awaiting:in_app', 'offer.issued:email', 'disbursement.awaiting_release:in_app', 'facility.booked:email', 'facility.booked.staff:in_app');

    // customer emails: sent, masked in the log, the advice attached to the booking email
    $emails = $log->where('channel', 'email');
    expect($emails->pluck('status')->unique()->values()->all())->toBe(['sent'])
        ->and($emails->first()->recipient_hint)->toBe('a**********@example.ng');
    $mail = collect(sentMail());
    $booking = $mail->first(fn (Email $m) => str_contains((string) $m->getSubject(), 'has been disbursed'));
    expect($mail->map(fn (Email $m) => $m->getTo()[0]->getAddress())->unique()->values()->all())->toBe(['amaka.nwosu@example.ng'])
        ->and($booking)->not->toBeNull()
        ->and($booking->getTextBody())->toContain('NGN 2,955,000.00')
        ->and(collect($booking->getAttachments())->map(fn ($a) => $a->getFilename())->all())->toContain(DB::table('document_versions')->where('id', DB::table('communications')->where('event', 'facility.booked')->value(DB::raw("attachments->0->>'version_id'")))->value('filename'));

    // staff routing: approvers (not the originator), checker (not the maker), originator on booking
    expect($log->where('event', 'approval.awaiting')->pluck('recipient_user_id')->all())->toBe([$this->j->approver->id])
        ->and($log->where('event', 'disbursement.awaiting_release')->pluck('recipient_user_id')->all())->toBe([$this->j->checker->id])
        ->and($log->where('event', 'facility.booked.staff')->pluck('recipient_user_id')->all())->toBe([$this->j->rm->id]);

    // the communication log through the API
    $this->login($this->j->rm);
    $api = $this->api('GET', "/api/v1/applications/{$app['id']}/communications")->assertOk()->json('data');
    expect(collect($api)->firstWhere('event', 'facility.booked'))->toMatchArray(['channel' => 'email', 'audience' => 'customer', 'status' => 'sent'])
        ->and(collect($api)->firstWhere('event', 'facility.booked')['template'])->toMatchArray(['source' => 'default', 'language' => 'en']);
    expect($booked['status'])->toBe('booked');
})->group('FR-NTF-001', 'FR-NTF-005', 'FR-DSB-011');

it('gives staff an inbox with unread count and read marking, private to each recipient', function () {
    $app = $this->j->approved();
    $this->login($this->j->approver);
    $inbox = $this->api('GET', '/api/v1/me/notifications')->assertOk();
    expect($inbox->json('meta.unread'))->toBe(1)
        ->and($inbox->json('data.0'))->toMatchArray(['event' => 'approval.awaiting', 'link' => "/applications/{$app['id']}"])
        ->and($inbox->json('data.0.title'))->toBe("Approval needed: {$app['reference']}");
    $id = $inbox->json('data.0.id');

    // someone else cannot touch it
    $this->login($this->j->ops);
    $this->api('POST', "/api/v1/me/notifications/{$id}/actions/read")->assertNotFound();
    expect($this->api('GET', '/api/v1/me/notifications')->json('meta.unread'))->toBe(0);

    $this->login($this->j->approver);
    $this->api('POST', "/api/v1/me/notifications/{$id}/actions/read")->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);
    expect($this->api('GET', '/api/v1/me/notifications?filter[unread]=true')->json('data'))->toBe([]);
})->group('FR-NTF-001');

it('uses a tenant template once activated through maker-checker, and validates templates (FR-NTF-002)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/notification.template', ['key' => 'application.received', 'name' => 'Received'])->assertCreated()->json('data');
    $bad = ['event' => 'application.received', 'audience' => 'customer', 'languages' => ['en' => ['email' => ['subject' => 'Hi {{ salary }}', 'body' => 'x'], 'telegram' => ['body' => 'y']]]];
    $errors = $this->api('POST', "/api/v1/config-artifacts/notification.template/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.languages.en.email.subject', 'content.languages.en.telegram');

    $version = LendingFixtures::activateConfig($this, 'notification.template', 'application.received', 'Received', [
        'event' => 'application.received', 'audience' => 'customer',
        'languages' => ['en' => ['email' => ['subject' => 'Ẹ ku orire: {{ reference }} received', 'body' => 'Hello {{ applicant_name }}, we have your application {{ reference }}.']]],
    ]);
    $app = $this->j->approved();
    $c = comms($app['id'])->firstWhere('event', 'application.received');
    expect($c->subject)->toBe("Ẹ ku orire: {$app['reference']} received")
        ->and($c->template_source)->toBe('tenant')
        ->and($c->template_version_id)->toBe($version['version']['id']);
})->group('FR-NTF-002');

it('suppresses duplicates, notifies compliance of screening hits, and reminds before an offer expires (FR-NTF-006, FR-OFR-007)', function () {
    $app = $this->j->approved();
    $offer = $this->j->issueOffer($app);
    $bus = app(CommandBus::class);
    $system = Principal::system($this->currentTenantFixture()->id, SystemIdentity::Workflow);
    $first = $bus->dispatch(new Notify('screening.alert', $app['id'], 'run-1'), $system);
    $again = $bus->dispatch(new Notify('screening.alert', $app['id'], 'run-1'), $system);
    expect($first)->toBe(['sent' => 1, 'suppressed' => 0])->and($again)->toBe(['sent' => 0, 'suppressed' => 1])
        ->and(comms($app['id'])->where('event', 'screening.alert')->pluck('recipient_user_id')->all())->toBe([$this->compliance->id]);

    Artisan::call('notifications:reminders');
    expect(comms($app['id'])->where('event', 'offer.expiring')->count())->toBe(0);
    $this->travel(28)->days();
    Artisan::call('notifications:reminders');
    Artisan::call('notifications:reminders');
    $reminder = comms($app['id'])->where('event', 'offer.expiring');
    expect($reminder)->toHaveCount(1)
        ->and($reminder->first()->subject)->toContain(substr($offer['valid_until'], 0, 10));
})->group('FR-NTF-006', 'FR-OFR-007');

it('logs a customer message it could not send', function () {
    $this->login($this->j->rm);
    $p = LendingFixtures::person($this, 'Chinedu', 'Eze', '22298765432', $this->j->org['lagos']['id']);
    $app = $this->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $this->j->org['le']['id'], 'org_unit_id' => $this->j->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $p['id'],
        'requested_amount' => '2000000.00', 'tenor_months' => 12, 'purpose' => 'Stock',
    ])->assertCreated()->json('data');
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $this->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag])->assertOk();
    $c = comms($app['id'])->firstWhere('event', 'application.received');
    expect($c->status)->toBe('failed')->and($c->error)->toContain('No email address');
})->group('FR-NTF-005');

it('texts customers through the sms port when SMS is enabled, masked in the log, retrying a gateway outage once only (FR-NTF-001)', function () {
    config(['fundly.sms.enabled' => true]);
    $app = $this->j->approved();
    $sms = DB::table('communications')->where('application_id', $app['id'])->where('channel', 'sms')->first();
    expect($sms)->not->toBeNull()
        ->and($sms->status)->toBe('queued')
        ->and($sms->recipient_hint)->toBe('+234*******677')
        ->and($sms->body)->toContain("loan application {$app['reference']}");

    // a gateway outage: retried later, then sent once with the same reference
    DB::table('adapter_bindings')->where('port', 'sms')->update(['config' => json_encode(['unavailable' => true])]);
    LendingFixtures::drainOutbox($this);
    expect(DB::table('communications')->where('id', $sms->id)->first())->status->toBe('queued');
    DB::table('adapter_bindings')->where('port', 'sms')->update(['config' => json_encode([])]);
    $this->travel(10)->minutes();
    LendingFixtures::drainOutbox($this);
    $sent = DB::table('communications')->where('id', $sms->id)->first();
    expect($sent->status)->toBe('sent')->and($sent->provider_ref)->toStartWith('sim-')->and($sent->attempts)->toBe(2);
    $calls = DB::table('integration_calls')->where('port', 'sms')->orderBy('started_at')->get();
    expect($calls->pluck('outcome')->all())->toBe(['retryable', 'success'])
        ->and($calls->pluck('idempotency_key')->unique()->all())->toBe([$sms->id])
        ->and((string) $calls->last()->request)->toContain('234802****677')->not->toContain('loan application');

    // the API log shows the channel
    $this->login($this->j->rm);
    expect(collect($this->api('GET', "/api/v1/applications/{$app['id']}/communications")->assertOk()->json('data'))->firstWhere('channel', 'sms'))->toMatchArray(['status' => 'sent', 'audience' => 'customer']);
})->group('FR-NTF-001', 'FR-NTF-005');

it('fails a text the gateway definitely refuses, and sends none when SMS is off', function () {
    $app = $this->j->approved();
    expect(DB::table('communications')->where('application_id', $app['id'])->where('channel', 'sms')->count())->toBe(0);

    config(['fundly.sms.enabled' => true]);
    DB::table('adapter_bindings')->where('port', 'sms')->update(['config' => json_encode(['reject_numbers' => ['2348025566677']])]);
    $this->j->issueOffer($app);
    LendingFixtures::drainOutbox($this);
    $sms = DB::table('communications')->where('application_id', $app['id'])->where('channel', 'sms')->where('event', 'offer.issued')->first();
    expect($sms->status)->toBe('failed')->and($sms->error)->toContain('SMS.REQUEST.INVALID_NUMBER');

    // SMS template text is validated like the rest
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/notification.template', ['key' => 'offer.issued', 'name' => 'Offer'])->assertCreated()->json('data');
    $errors = $this->api('POST', "/api/v1/config-artifacts/notification.template/{$artifact['id']}/versions", ['content' => [
        'event' => 'offer.issued', 'audience' => 'customer', 'languages' => ['en' => ['sms' => ['body' => str_repeat('a', 460)]]],
    ]])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.languages.en.sms.body');
})->group('FR-NTF-001');

function optInWhatsApp(object $t, array $app): void
{
    $t->login($t->j->rm);
    LendingFixtures::consent($t, ['id' => $app['primary_applicant']['party_id']], ['whatsapp_messaging']);
}

it('sends WhatsApp templates only to customers who opted in, alongside or instead of SMS (FR-NTF-001, LOS-FR-304)', function () {
    config(['fundly.sms.enabled' => true, 'fundly.whatsapp.enabled' => true]);
    $app = $this->j->approved();
    // no opt-in yet: SMS and email only
    expect(comms($app['id'])->where('event', 'application.received')->pluck('channel')->sort()->values()->all())->toBe(['email', 'sms']);

    optInWhatsApp($this, $app);
    $this->j->issueOffer($app);
    LendingFixtures::drainOutbox($this);
    $offer = comms($app['id'])->where('event', 'offer.issued');
    expect($offer->pluck('channel')->sort()->values()->all())->toBe(['email', 'sms', 'whatsapp']);
    $wa = $offer->firstWhere('channel', 'whatsapp');
    expect($wa->status)->toBe('sent')
        ->and($wa->subject)->toBe('fundly_offer_issued')
        ->and($wa->body)->toStartWith('Hello Amaka Nwosu, your loan offer')
        ->and($wa->provider_ref)->toStartWith('wa-sim-')
        ->and($wa->recipient_hint)->toBe('+234*******677');
    $call = DB::table('integration_calls')->where('port', 'whatsapp')->first();
    expect((string) $call->request)->toContain('fundly_offer_issued')->toContain('234802****677')->not->toContain('Hello Amaka');

    // configured to replace SMS: opted-in customers get WhatsApp only
    config(['fundly.whatsapp.replaces_sms' => true]);
    app(CommandBus::class)->dispatch(new Notify('offer.expiring', $app['id'], 'manual-1', ['valid_until' => '2026-11-13', 'offer_sequence' => 1]), Principal::system($this->currentTenantFixture()->id, SystemIdentity::Workflow));
    expect(comms($app['id'])->where('event', 'offer.expiring')->pluck('channel')->sort()->values()->all())->toBe(['email', 'whatsapp']);
})->group('FR-NTF-001', 'LOS-FR-304');

it('never resends a WhatsApp message whose outcome is unknown, and honours a withdrawal before sending', function () {
    config(['fundly.whatsapp.enabled' => true]);
    $app = $this->j->approved();
    optInWhatsApp($this, $app);
    $bus = app(CommandBus::class);
    $system = Principal::system($this->currentTenantFixture()->id, SystemIdentity::Workflow);

    DB::table('adapter_bindings')->where('port', 'whatsapp')->update(['config' => json_encode(['timeout' => true])]);
    $bus->dispatch(new Notify('offer.expiring', $app['id'], 'r-1', ['valid_until' => '2026-11-13', 'offer_sequence' => 1]), $system);
    LendingFixtures::drainOutbox($this);
    $this->travel(30)->minutes();
    LendingFixtures::drainOutbox($this);
    $wa = comms($app['id'])->where('event', 'offer.expiring')->firstWhere('channel', 'whatsapp');
    expect($wa->status)->toBe('failed')->and($wa->error)->toContain('not resent')
        ->and(DB::table('integration_calls')->where('port', 'whatsapp')->count())->toBe(1);

    // consent withdrawn between queueing and sending
    DB::table('adapter_bindings')->where('port', 'whatsapp')->update(['config' => json_encode([])]);
    $bus->dispatch(new Notify('offer.expiring', $app['id'], 'r-2', ['valid_until' => '2026-11-13', 'offer_sequence' => 1]), $system);
    $this->login($this->j->rm);
    $this->api('POST', "/api/v1/parties/{$app['primary_applicant']['party_id']}/consents", ['purpose' => 'whatsapp_messaging', 'action' => 'withdraw', 'channel' => 'branch', 'terms_version' => 'T&C-2026.1'])->assertCreated();
    LendingFixtures::drainOutbox($this);
    $second = DB::table('communications')->where('dedupe_key', "offer.expiring:{$app['id']}:r-2:whatsapp:{$app['primary_applicant']['party_id']}")->first();
    expect($second->status)->toBe('failed')->and($second->error)->toContain('consent was withdrawn')
        ->and(DB::table('integration_calls')->where('port', 'whatsapp')->count())->toBe(1);
})->group('FR-NTF-001');
