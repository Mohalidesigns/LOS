<?php

declare(strict_types=1);

use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    // the approver also holds disbursement:check, so FR-DSB-009's "checker ≠ approver" is what refuses them
    $this->j = Journey::setUp($this, [Permission::DisbursementCheck, Permission::ChangeRequestRead]);
    $this->maker = $this->userWith([Permission::ApplicationView, Permission::DisbursementMake]);
    $this->checker = $this->userWith([Permission::ApplicationView, Permission::DisbursementCheck, Permission::DisbursementRetry, Permission::ReconciliationRun, Permission::ChangeRequestRead]);
});

function cbaFaults(array $rules): void
{
    AdapterBinding::query()->where('port', 'core_banking')->update(['fault_script' => ['rules' => $rules]]);
}

function dsbStatus(string $applicationId): string
{
    return (string) DB::table('applications')->where('id', $applicationId)->value('canonical_status');
}

/** Maker makes the instruction, the checker releases it; returns the instruction as released. */
function makeAndRelease(object $t, string $applicationId): array
{
    $t->login($t->maker);
    $d = $t->api('POST', "/api/v1/applications/{$applicationId}/disbursements")->assertCreated()->json('data');
    $t->login($t->checker);
    $t->api('POST', "/api/v1/change-requests/{$d['change_request_id']}/actions/approve", ['reason' => 'Instruction matches the agreement'])->assertOk();

    return $d;
}

function instruction(object $t, string $applicationId): array
{
    return $t->api('GET', "/api/v1/applications/{$applicationId}/disbursements")->assertOk()->json('data.0');
}

function disbursePostings(string $loanAccountNo): int
{
    return DB::table('cba_simulator_records')->where('kind', 'posting')->get()->filter(fn ($r) => (json_decode((string) $r->data, true)['account_no'] ?? null) === $loanAccountNo && str_contains((string) json_decode((string) $r->data, true)['reference'], ':disburse'))->count();
}

it('runs demo steps 15-16: maker-checker release refuses the approver, then the saga books once despite a timeout', function () {
    $app = $this->j->readyForDisbursement();
    expect(dsbStatus($app['id']))->toBe('ready_for_disbursement');
    cbaFaults([['operation' => 'postings.disburse', 'fault' => 'timeout_then_success', 'times' => 1]]);

    $this->login($this->maker);
    $d = $this->api('POST', "/api/v1/applications/{$app['id']}/disbursements")->assertCreated()->json('data');
    expect($d)->toMatchArray(['status' => 'pending_release', 'sequence' => 1, 'value_date' => now()->toDateString()])
        ->and($d['principal'])->toBe(['amount' => '3000000.0000', 'currency' => 'NGN'])
        ->and($d['net'])->toBe(['amount' => '2955000.0000', 'currency' => 'NGN'])
        ->and(collect($d['fees'])->pluck('code')->all())->toBe(['MGMT', 'CRI'])
        ->and($d['destination'])->toMatchArray(['bank_code' => '058', 'account' => '******6789', 'account_name' => 'NWOSU AMAKA']);
    // one live instruction at a time
    $this->api('POST', "/api/v1/applications/{$app['id']}/disbursements")->assertStatus(422);
    // four-eyes: not the maker …
    $this->api('POST', "/api/v1/change-requests/{$d['change_request_id']}/actions/approve", ['reason' => 'self'])->assertForbidden();
    // … and not someone who approved the credit (FR-DSB-009)
    $this->login($this->j->approver);
    $refused = $this->api('POST', "/api/v1/change-requests/{$d['change_request_id']}/actions/approve", ['reason' => 'I approved it'])->assertForbidden();
    expect($refused->json('detail'))->toContain('decision you took part in');
    // a third user releases; the sign-in counts as the step-up
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$d['change_request_id']}/actions/approve", ['reason' => 'Instruction matches the agreement'])->assertOk();
    $released = instruction($this, $app['id']);
    expect($released['status'])->toBe('pending_cba')->and($released['released_by']['id'])->toBe($this->checker->id)
        ->and(dsbStatus($app['id']))->toBe('disbursing');
    // the release re-ran the pre-disbursement check (D-053)
    expect(DB::table('pre_disbursement_checks')->where('application_id', $app['id'])->count())->toBe(2);

    LendingFixtures::drainOutbox($this);
    $booked = instruction($this, $app['id']);
    $disburse = collect($booked['steps'])->firstWhere('step', 'disburse');
    expect($booked['status'])->toBe('booked')
        ->and(dsbStatus($app['id']))->toBe('booked')
        ->and(collect($booked['steps'])->pluck('step')->all())->toBe(['ensure_customer', 'create_loan_account', 'disburse', 'post_fees', 'schedule_check', 'book', 'advice'])
        ->and($disburse['detail'])->toMatchArray(['outcome_unknown' => 'CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME', 'resolved_by_lookup' => true])
        ->and($booked['cba']['fee_posting_refs'])->toHaveCount(2)
        ->and($booked['variance']['within_tolerance'])->toBeTrue()
        ->and($booked['schedule'])->toHaveCount(12)
        ->and($booked['advice'])->not->toBeNull()
        ->and(disbursePostings($booked['cba']['loan_account_no']))->toBe(1);
    expect(DB::table('documents')->where('application_id', $app['id'])->where('document_type', 'DISBURSEMENT_ADVICE')->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'disbursement.booked')->where('entity_id', $d['id'])->exists())->toBeTrue();
    // the step log is evidence
    expect(fn () => DB::transaction(fn () => DB::table('disbursement_steps')->where('disbursement_id', $d['id'])->delete()))->toThrow(QueryException::class);
})->group('FR-DSB-001', 'FR-DSB-004', 'FR-DSB-005', 'FR-DSB-006', 'FR-DSB-009', 'FR-DSB-011', 'LOS-FR-313');

it('parks a transient failure as failed_retryable and completes on the outbox retry without posting twice (FR-DSB-006/007)', function () {
    $app = $this->j->readyForDisbursement();
    cbaFaults([['operation' => 'postings.disburse', 'fault' => 'timeout', 'times' => 1]]);
    makeAndRelease($this, $app['id']);
    LendingFixtures::drainOutbox($this);
    $failed = instruction($this, $app['id']);
    expect($failed)->toMatchArray(['status' => 'failed_retryable'])
        ->and($failed['last_error']['code'])->toBe('CBA.TRANSPORT.NOT_APPLIED')
        ->and($this->api('GET', '/api/v1/disbursement-exceptions')->json('data.0.id'))->toBe($failed['id']);

    $this->travel(10)->minutes();
    LendingFixtures::drainOutbox($this);
    $booked = instruction($this, $app['id']);
    expect($booked['status'])->toBe('booked')->and($booked['saga_runs'])->toBe(2)
        ->and(disbursePostings($booked['cba']['loan_account_no']))->toBe(1)
        ->and(collect($booked['steps'])->where('step', 'create_loan_account')->count())->toBe(1);
})->group('FR-DSB-005', 'FR-DSB-006', 'FR-DSB-007');

it('compensates a definite rejection by closing the account, and allows a fresh instruction (FR-CBA-009)', function () {
    $app = $this->j->readyForDisbursement();
    cbaFaults([['operation' => 'postings.disburse', 'fault' => 'reject', 'code' => 'CBA.POSTING.REJECTED', 'times' => 1]]);
    $first = makeAndRelease($this, $app['id']);
    LendingFixtures::drainOutbox($this);
    $dead = instruction($this, $app['id']);
    expect($dead['status'])->toBe('compensated')
        ->and(collect($dead['steps'])->firstWhere('step', 'compensate')['outcome'])->toBe('compensated')
        ->and(dsbStatus($app['id']))->toBe('disbursing');
    $this->api('POST', "/api/v1/disbursements/{$first['id']}/actions/retry")->assertStatus(422);

    $second = makeAndRelease($this, $app['id']);
    expect($second['sequence'])->toBe(2);
    LendingFixtures::drainOutbox($this);
    expect(instruction($this, $app['id'])['status'])->toBe('booked')->and(dsbStatus($app['id']))->toBe('booked');
})->group('FR-CBA-009', 'FR-DSB-007');

it('blocks booking on a schedule variance and books after the cause is fixed and the instruction retried (LOS-FR-313)', function () {
    $app = $this->j->readyForDisbursement();
    $offer = DB::table('offers')->where('application_id', $app['id'])->where('status', 'accepted')->first();
    $schedule = json_decode((string) $offer->schedule, true);
    $original = $schedule;
    $schedule[0]['total'] = (string) ((float) $schedule[0]['total'] + 50);
    DB::table('offers')->where('id', $offer->id)->update(['schedule' => json_encode($schedule)]);

    makeAndRelease($this, $app['id']);
    LendingFixtures::drainOutbox($this);
    $parked = instruction($this, $app['id']);
    expect($parked['status'])->toBe('failed_intervention')
        ->and($parked['last_error']['code'])->toBe('LOS.SCHEDULE.VARIANCE')
        ->and($parked['variance']['mismatches'][0])->toMatchArray(['number' => 1, 'field' => 'total'])
        ->and($parked['schedule'])->toBe([]);

    DB::table('offers')->where('id', $offer->id)->update(['schedule' => json_encode($original)]);
    $this->login($this->maker);
    $this->api('POST', "/api/v1/disbursements/{$parked['id']}/actions/retry")->assertForbidden();
    $this->login($this->checker);
    $this->api('POST', "/api/v1/disbursements/{$parked['id']}/actions/retry")->assertOk()->assertJsonPath('data.status', 'failed_retryable');
    LendingFixtures::drainOutbox($this);
    $booked = instruction($this, $app['id']);
    expect($booked['status'])->toBe('booked')->and(disbursePostings($booked['cba']['loan_account_no']))->toBe(1);
})->group('LOS-FR-313', 'FR-DSB-007');

it('runs demo step 18: reconciliation matches every posting, then flags an orphan CBA posting as a break (FR-DSB-008)', function () {
    $app = $this->j->readyForDisbursement();
    makeAndRelease($this, $app['id']);
    LendingFixtures::drainOutbox($this);
    $this->login($this->checker);
    $clean = $this->api('POST', '/api/v1/reconciliation-runs')->assertCreated()->json('data');
    expect($clean)->toMatchArray(['los_postings' => 3, 'cba_postings' => 3, 'matched' => 3, 'breaks' => 0]);

    $binding = AdapterBinding::query()->where('port', 'core_banking')->firstOrFail();
    $binding->forceFill(['config' => $binding->config + ['orphan_postings' => [[
        'posting_ref' => 'SIMP9999999999', 'reference' => 'MANUAL-ADJ-1', 'account_no' => '9000000099', 'amount' => '125000.0000', 'currency' => 'NGN', 'status' => 'posted', 'value_date' => now()->toDateString(),
    ]]]])->save();
    $second = $this->api('POST', '/api/v1/reconciliation-runs')->assertCreated()->json('data');
    expect($second)->toMatchArray(['matched' => 3, 'breaks' => 1])
        ->and($second['break_items'][0])->toMatchArray(['kind' => 'missing_in_los', 'posting_ref' => 'SIMP9999999999', 'cba_amount' => '125000.0000', 'status' => 'open']);
    $open = $this->api('GET', '/api/v1/reconciliation-breaks')->assertOk()->json('data');
    expect($open)->toHaveCount(1);
    $this->api('POST', "/api/v1/reconciliation-breaks/{$open[0]['id']}/actions/resolve", ['note' => 'Manual adjustment by CBA operations; journal attached'])->assertOk()->assertJsonPath('data.status', 'resolved');
    expect($this->api('GET', '/api/v1/reconciliation-breaks')->json('data'))->toBe([]);
})->group('FR-DSB-008', 'FR-CBA-017');

it('refuses an instruction while release is blocked', function () {
    $app = $this->j->readyForDisbursement();
    // the check goes stale after 24 hours
    $this->travel(25)->hours();
    $this->login($this->maker);
    $this->api('POST', "/api/v1/applications/{$app['id']}/disbursements")->assertStatus(422)->assertJsonPath('blockers.0', fn ($b) => str_contains($b, 'out of date'));
    $this->login($this->j->rm);
    $this->api('POST', "/api/v1/applications/{$app['id']}/disbursements")->assertForbidden();
})->group('FR-CPR-002', 'FR-DSB-009');
