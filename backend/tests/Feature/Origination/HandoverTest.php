<?php

declare(strict_types=1);

use Fundly\Integration\Webhooks\WebhookSigner;
use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->j = Journey::setUp($this);
    $this->integrator = $this->userWith([Permission::IntegrationRead, Permission::WebhookManage]);
    $this->servicing = $this->userWith([Permission::ApplicationView, Permission::HandoverAcknowledge]);
});

function registerEndpoint(object $t, string $url = 'https://servicing.bank.test/hooks', array $events = ['facility.created']): array
{
    $t->login($t->integrator);

    return $t->api('POST', '/api/v1/webhook-endpoints', ['name' => 'Servicing LMS', 'url' => $url, 'events' => $events])->assertCreated()->json('data');
}

it('runs demo step 17: booking hands the facility over and delivers a signed facility.created webhook', function () {
    Http::fake(['servicing.bank.test/*' => Http::response(['ok' => true], 200)]);
    $endpoint = registerEndpoint($this);
    expect($endpoint['secret'])->toStartWith('whsec_');
    // the secret is shown once only
    expect($this->api('GET', '/api/v1/webhook-endpoints')->json('data.0'))->not->toHaveKey('secret');

    $app = $this->j->readyForDisbursement();
    // a condition subsequent that must follow the loan into servicing
    $this->login($this->j->ops);
    $this->api('POST', "/api/v1/applications/{$app['id']}/conditions", ['kind' => 'subsequent', 'description' => 'Quarterly stock report for the first year', 'mandatory' => true, 'due_date' => now()->addDays(90)->toDateString()])->assertCreated();
    $booked = $this->j->book($app);
    expect($booked['status'])->toBe('booked');
    LendingFixtures::drainOutbox($this); // webhook deliveries

    // the package: exact JSON hashed, with terms, documents, CS and insurance
    $this->login($this->servicing);
    $handover = $this->api('GET', "/api/v1/applications/{$app['id']}/handover")->assertOk()->json('data');
    $pkg = $handover['package'];
    $c = $pkg['content'];
    expect($pkg['sha256'])->toBe(hash('sha256', (string) DB::table('handover_packages')->where('id', $pkg['id'])->value('package_json')))
        ->and($c['facility'])->toMatchArray(['loan_account_no' => $booked['cba']['loan_account_no'], 'tenor_months' => 12, 'value_date' => now()->toDateString()])
        ->and($c['facility']['schedule'])->toHaveCount(12)
        ->and($c['borrower']['cba_customer_id'])->toBe($booked['cba']['customer_id'])
        ->and(collect($c['documents'])->pluck('type')->all())->toContain('OFFER_LETTER', 'EXECUTED_AGREEMENT', 'DISBURSEMENT_ADVICE')
        ->and(collect($c['documents'])->every(fn ($d) => strlen($d['sha256']) === 64 && str_starts_with($d['href'], '/api/v1/documents/')))->toBeTrue()
        ->and($c['conditions_subsequent'])->toHaveCount(1)
        ->and($c['conditions_subsequent'][0]['description'])->toBe('Quarterly stock report for the first year')
        ->and($c['insurance'][0])->toMatchArray(['fee_code' => 'CRI', 'cover_until' => end($c['facility']['schedule'])['due_date']])
        ->and($c['collateral'])->toBe([])
        ->and($handover['retention'])->toMatchArray(['category' => 'origination_record', 'trigger' => 'fixed_from_booking', 'retain_until' => now()->addYears(5)->toDateString(), 'legal_hold' => false]);

    // the webhook: delivered, signed, identifiers and terms only
    expect($handover['deliveries'])->toHaveCount(1)
        ->and($handover['deliveries'][0])->toMatchArray(['event_type' => 'facility.created', 'status' => 'delivered', 'attempts' => 1, 'last_http_status' => 200]);
    Http::assertSent(function (Request $r) use ($endpoint, $pkg, $app): bool {
        $body = $r->body();
        $data = json_decode($body, true);

        return $r->url() === 'https://servicing.bank.test/hooks'
            && WebhookSigner::verify($endpoint['secret'], $r->header('Fundly-Signature')[0], $body, now()->getTimestamp())
            && $r->header('Fundly-Event-Type')[0] === 'facility.created'
            && $data['data']['application_id'] === $app['id']
            && $data['data']['handover_package']['sha256'] === $pkg['sha256']
            && ! str_contains($body, 'Amaka');
    });

    // servicing acknowledges, once
    $ack = $this->api('POST', "/api/v1/handover-packages/{$pkg['id']}/actions/acknowledge", ['note' => 'Taken over by Lagos Island servicing'])->assertOk()->json('data');
    expect($ack['acknowledgement']['by']['id'])->toBe($this->servicing->id);
    $this->api('POST', "/api/v1/handover-packages/{$pkg['id']}/actions/acknowledge", ['note' => 'Again please'])->assertStatus(422);
    // the package is evidence
    expect(fn () => DB::transaction(fn () => DB::table('handover_packages')->where('id', $pkg['id'])->update(['sha256' => str_repeat('0', 64)])))->toThrow(QueryException::class);
    // nothing is created twice
    Artisan::call('handover:create');
    expect(DB::table('handover_packages')->where('application_id', $app['id'])->count())->toBe(1);
})->group('FR-HND-001', 'FR-HND-002', 'FR-HND-003', 'FR-HND-004', 'FR-NTF-001');

it('retries a failing endpoint with backoff, logs every attempt, and can redeliver', function () {
    Http::fake(['servicing.bank.test/*' => Http::sequence()->push('down', 503)->push(['ok' => true], 200)->push(['ok' => true], 200)]);
    registerEndpoint($this);
    $app = $this->j->readyForDisbursement();
    $this->j->book($app);
    LendingFixtures::drainOutbox($this);
    $this->login($this->integrator);
    $failing = $this->api('GET', '/api/v1/webhook-deliveries?filter[subject_type]=application&filter[subject_id]='.$app['id'])->assertOk()->json('data.0');
    expect($failing)->toMatchArray(['status' => 'failing', 'attempts' => 1, 'last_http_status' => 503]);

    $this->travel(10)->minutes();
    LendingFixtures::drainOutbox($this);
    $this->login($this->integrator);
    $delivered = $this->api('GET', '/api/v1/webhook-deliveries')->json('data.0');
    expect($delivered)->toMatchArray(['status' => 'delivered', 'attempts' => 2, 'last_http_status' => 200]);

    $this->api('POST', "/api/v1/webhook-deliveries/{$delivered['id']}/actions/redeliver")->assertOk()->assertJsonPath('data.status', 'pending');
    LendingFixtures::drainOutbox($this);
    Http::assertSentCount(3);
})->group('FR-NTF-001');

it('skips disabled endpoints and refuses insecure ones', function () {
    Http::fake();
    $endpoint = registerEndpoint($this);
    $this->api('POST', '/api/v1/webhook-endpoints', ['name' => 'Plain', 'url' => 'http://lms.example.com/hooks', 'events' => ['facility.created']])->assertStatus(422)->assertJsonValidationErrors(['url']);
    $this->api('POST', '/api/v1/webhook-endpoints', ['name' => 'Bad events', 'url' => 'https://lms.example.com/hooks', 'events' => ['Facility Created']])->assertStatus(422);
    $this->api('POST', "/api/v1/webhook-endpoints/{$endpoint['id']}/actions/disable")->assertOk()->assertJsonPath('data.status', 'disabled');
    $app = $this->j->readyForDisbursement();
    $this->j->book($app);
    LendingFixtures::drainOutbox($this);
    Http::assertNothingSent();
    expect(DB::table('handover_packages')->where('application_id', $app['id'])->exists())->toBeTrue();
})->group('FR-NTF-001');

it('verifies signatures strictly', function () {
    $header = WebhookSigner::header('whsec_test', 1_800_000_000, '{"a":1}');
    expect(WebhookSigner::verify('whsec_test', $header, '{"a":1}', 1_800_000_010))->toBeTrue()
        ->and(WebhookSigner::verify('whsec_test', $header, '{"a":2}', 1_800_000_010))->toBeFalse()
        ->and(WebhookSigner::verify('whsec_other', $header, '{"a":1}', 1_800_000_010))->toBeFalse()
        ->and(WebhookSigner::verify('whsec_test', $header, '{"a":1}', 1_800_000_900))->toBeFalse();
});
