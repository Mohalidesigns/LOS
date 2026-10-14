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
    $bad = ['event' => 'application.received', 'audience' => 'customer', 'languages' => ['en' => ['email' => ['subject' => 'Hi {{ salary }}', 'body' => 'x'], 'sms' => ['body' => 'y']]]];
    $errors = $this->api('POST', "/api/v1/config-artifacts/notification.template/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.languages.en.email.subject', 'content.languages.en.sms');

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
