<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Workflow\Domain\BusinessCalendar;
use Fundly\Modules\Workflow\Domain\DefaultWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->j = Journey::setUp($this);
    $this->ops2 = $this->userWith([Permission::ApplicationView, Permission::OfferPrepare]);
    $this->supervisor = $this->userWith([Permission::ApplicationView, Permission::TaskReassign], roleCode: 'branch_manager');
});

function workflowOf(object $t, string $applicationId): array
{
    return $t->api('GET', "/api/v1/applications/{$applicationId}/workflow")->assertOk()->json('data');
}

function actOn(object $t, string $applicationId, string $action, array $body = []): void
{
    $etag = (string) $t->api('GET', "/api/v1/applications/{$applicationId}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$applicationId}/actions/{$action}", $body, ['If-Match' => $etag])->assertOk();
}

it('opens one task per worked stage, routes it by the stage rule, and keeps the history (FR-WFL-001/003/004)', function () {
    $app = $this->j->approved();
    $this->login($this->j->rm);
    $wf = workflowOf($this, $app['id']);
    expect($wf['workflow'])->toMatchArray(['key' => 'sme-standard', 'source' => 'default', 'version_id' => null])
        ->and($wf['current'])->toMatchArray(['status' => 'open', 'assignee' => null, 'strategy' => 'queue'])
        ->and($wf['current']['stage'])->toBe(['code' => 'offer_preparation', 'name' => 'Offer preparation', 'status' => 'approved'])
        ->and($wf['current']['sla']['state'])->toBe('on_track');
    $byStage = collect($wf['tasks'])->keyBy('stage.code');
    // KYC stays with the originator; assessment goes to the least-loaded analyst (never the originator); approval is a pull queue
    expect($byStage['kyc']['assignee']['id'])->toBe($this->j->rm->id)
        ->and($byStage['kyc'])->toMatchArray(['status' => 'completed', 'close_reason' => 'status:documentation'])
        ->and($byStage['assessment']['assignee']['id'])->toBe($this->j->analyst->id)
        ->and($byStage['assessment']['history'][0])->toMatchArray(['kind' => 'routed'])
        ->and($byStage['approval']['assignee'])->toBeNull()
        ->and(collect($wf['tasks'])->pluck('stage.code')->all())->not->toContain('intake', 'prequalification', 'recommended');
    // one open task at a time
    expect(DB::table('workflow_tasks')->where('application_id', $app['id'])->where('status', 'open')->count())->toBe(1);
    // the analyst was told
    expect(DB::table('communications')->where('event', 'task.assigned')->where('recipient_user_id', $this->j->analyst->id)->exists())->toBeTrue();
})->group('FR-WFL-001', 'FR-WFL-003', 'FR-WFL-004');

it('lets eligible users pull from the queue, release and be reassigned with attribution, in bulk too (FR-WFL-004/005/009)', function () {
    $app = $this->j->approved();
    $this->login($this->j->ops);
    $queue = $this->api('GET', '/api/v1/me/tasks?filter[view]=queue')->assertOk()->json();
    $task = collect($queue['data'])->firstWhere('application.id', $app['id']);
    expect($task)->not->toBeNull()->and($queue['meta']['can_reassign'])->toBeFalse();

    // only someone holding the stage permission can claim
    $this->login($this->j->rm);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/claim")->assertForbidden();
    $this->login($this->j->ops);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/claim")->assertOk()->assertJsonPath('data.assignee.id', $this->j->ops->id);
    expect($this->api('GET', '/api/v1/me/tasks')->json('meta.counts.mine'))->toBe(1);
    $this->login($this->ops2);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/claim")->assertStatus(422);
    $this->login($this->j->ops);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/release", ['reason' => 'Going on leave'])->assertOk()->assertJsonPath('data.assignee', null);

    // a supervisor moves it, with a reason, only to someone who can work it
    $this->login($this->supervisor);
    expect(collect($this->api('GET', '/api/v1/me/tasks?filter[view]=team')->json('data'))->pluck('id')->all())->toContain($task['id']);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/reassign", ['assignee_id' => $this->j->rm->id, 'reason' => 'Cover for the team'])->assertStatus(422);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/reassign", ['assignee_id' => $this->ops2->id, 'reason' => 'Cover while Ope is on leave'])->assertOk();
    $this->login($this->j->ops);
    $this->api('POST', "/api/v1/tasks/{$task['id']}/actions/reassign", ['assignee_id' => $this->j->ops->id, 'reason' => 'Taking it back'])->assertForbidden();
    $history = collect(workflowOf($this, $app['id'])['current']['history'])->pluck('kind')->all();
    expect($history)->toBe(['claimed', 'released', 'reassigned'])
        ->and(DB::table('communications')->where('event', 'task.assigned')->where('recipient_user_id', $this->ops2->id)->exists())->toBeTrue();

    // bulk: all or nothing
    $this->login($this->ops2);
    $this->api('POST', '/api/v1/tasks/actions/bulk-assign', ['task_ids' => [$task['id']]])->assertStatus(422);
    $this->login($this->supervisor);
    $this->api('POST', '/api/v1/tasks/actions/bulk-assign', ['task_ids' => [$task['id']], 'assignee_id' => $this->j->ops->id, 'reason' => 'Balancing the queue'])->assertOk()->assertJsonPath('data.assigned', 1);

    // saved views
    $this->login($this->j->ops);
    $v = $this->api('POST', '/api/v1/me/task-views', ['name' => 'Breached offers', 'filters' => ['view' => 'mine', 'stage' => 'offer_preparation', 'sla' => 'breached']])->assertCreated()->json('data');
    expect($this->api('GET', '/api/v1/me/task-views')->json('data.0.filters'))->toEqualCanonicalizing(['view' => 'mine', 'stage' => 'offer_preparation', 'sla' => 'breached']);
    $this->api('DELETE', "/api/v1/me/task-views/{$v['id']}")->assertNoContent();
})->group('FR-WFL-004', 'FR-WFL-005', 'FR-WFL-009');

it('warns, breaches and escalates in business hours, and pauses the clock while on hold (FR-WFL-006/008)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 08:00', 'Africa/Lagos')); // the next Monday, opening of business
    $app = $this->j->approved();
    $this->login($this->j->rm);
    $task = workflowOf($this, $app['id'])['current'];
    $opened = Carbon::parse($task['opened_at']);
    $warn = Carbon::parse($task['sla']['warn_at']);
    $breach = Carbon::parse($task['sla']['breach_at']);
    // 6 and 8 business hours, all inside Monday 08:00–17:00
    expect((int) $opened->diffInMinutes($warn))->toBe(360)->and((int) $opened->diffInMinutes($breach))->toBe(480)
        ->and($breach->copy()->setTimezone('Africa/Lagos')->format('D'))->toBe('Mon');

    $this->travelTo($warn->copy()->addMinutes(5));
    Artisan::call('workflow:sla');
    $this->login($this->j->rm); // sessions idle out over hours
    expect(workflowOf($this, $app['id'])['current']['sla']['state'])->toBe('at_risk')
        ->and(DB::table('communications')->where('event', 'task.sla_at_risk')->pluck('recipient_user_id')->all())->toContain($this->j->ops->id);

    // on hold over the night: the clock stops
    actOn($this, $app['id'], 'hold', ['reason_code' => 'AWAITING_CUSTOMER', 'reason_text' => 'Customer travelling']);
    $heldAt = Carbon::parse((string) DB::table('workflow_tasks')->where('id', $task['id'])->value('paused_at'));
    expect(workflowOf($this, $app['id'])['current']['sla']['state'])->toBe('paused');
    $this->travelTo(Carbon::parse('2026-10-20 12:00', 'Africa/Lagos'));
    Artisan::call('workflow:sla');
    $this->login($this->j->rm);
    actOn($this, $app['id'], 'resume');
    $resumed = workflowOf($this, $app['id'])['current'];
    $calendar = BusinessCalendar::lagos();
    $paused = $calendar->businessMinutesBetween($heldAt->toDateTimeImmutable(), now()->toDateTimeImmutable());
    // the breach moves by exactly the business time spent on hold (about 6 hours: Monday evening + Tuesday morning)
    expect($resumed['sla']['state'])->toBe('at_risk')->and($resumed['sla']['paused_minutes'])->toBe($paused)->and($paused)->toBeGreaterThan(300)->toBeLessThan(420)
        ->and(Carbon::parse($resumed['sla']['breach_at'])->getTimestamp())->toBe($calendar->addBusinessMinutes($breach->toDateTimeImmutable(), $paused)->getTimestamp());

    $this->travelTo(Carbon::parse($resumed['sla']['breach_at'])->addMinutes(5));
    Artisan::call('workflow:sla');
    $this->login($this->j->rm);
    expect(workflowOf($this, $app['id'])['current']['sla']['state'])->toBe('breached')
        ->and(DB::table('communications')->where('event', 'task.sla_breached')->pluck('recipient_user_id')->all())->toContain($this->supervisor->id)
        ->and(DB::table('audit_events')->where('action', 'workflow.sla_breached')->where('entity_id', $app['id'])->exists())->toBeTrue();
    // once only
    Artisan::call('workflow:sla');
    expect(DB::table('communications')->where('event', 'task.sla_breached')->where('recipient_user_id', $this->supervisor->id)->count())->toBe(1);
})->group('FR-WFL-006', 'FR-WFL-008');

it('validates workflows, pins each application to its version, and migrates only with audit (FR-WFL-001/012)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigActivateRequest]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/workflow.definition', ['key' => 'sme-standard', 'name' => 'SME workflow'])->assertCreated()->json('data');
    $bad = DefaultWorkflow::standard();
    $bad['stages'][3]['status'] = 'kyc_screening';
    $bad['stages'][6]['automation'] = 'advance';
    $bad['stages'][4]['sla'] = ['warn_hours' => 20, 'breach_hours' => 10];
    $errors = $this->api('POST', "/api/v1/config-artifacts/workflow.definition/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.stages.3.status', 'content.stages.6.automation', 'content.stages.4.sla');

    // A starts on the built-in workflow
    $a = $this->j->approved(vote: false);
    // the tenant's own: assessment becomes a pull queue
    $mine = DefaultWorkflow::standard();
    $mine['name'] = 'SME workflow v1';
    $mine['stages'][4]['assignment']['strategy'] = 'queue';
    $v = LendingFixtures::activateConfig($this, 'workflow.definition', 'sme-standard', 'SME workflow', $mine)['version'];

    $this->login($this->j->rm);
    expect(workflowOf($this, $a['id'])['workflow'])->toMatchArray(['source' => 'default', 'version_id' => null]);
    $p = LendingFixtures::person($this, 'Tunde', 'Bakare', '22211122233', $this->j->org['lagos']['id']);
    $b = $this->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $this->j->org['le']['id'], 'org_unit_id' => $this->j->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $p['id'],
        'requested_amount' => '1000000.00', 'tenor_months' => 12, 'purpose' => 'Working capital',
    ])->assertCreated()->json('data');
    actOn($this, $b['id'], 'submit');
    expect(workflowOf($this, $b['id'])['workflow'])->toMatchArray(['source' => 'tenant', 'version_id' => $v['id'], 'name' => 'SME workflow v1']);

    // A moves only when an administrator says so
    $this->api('POST', "/api/v1/applications/{$a['id']}/workflow/actions/migrate", ['reason' => 'Adopting the tenant workflow'])->assertForbidden();
    $this->login($author);
    $migrated = $this->api('POST', "/api/v1/applications/{$a['id']}/workflow/actions/migrate", ['reason' => 'Adopting the tenant workflow for all SME cases'])->assertOk()->json('data');
    expect($migrated['workflow'])->toMatchArray(['source' => 'tenant', 'version_id' => $v['id']])->and($migrated['workflow']['migrated_at'])->not->toBeNull();
    $this->api('POST', "/api/v1/applications/{$a['id']}/workflow/actions/migrate", ['reason' => 'Adopting the tenant workflow again'])->assertStatus(422);
    expect(DB::table('audit_events')->where('action', 'workflow.migrated')->where('entity_id', $a['id'])->exists())->toBeTrue();
})->group('FR-WFL-001', 'FR-WFL-012');

it('keeps a case-notes thread with mentions and customer-visible notes for originators only (FR-WFL-010)', function () {
    $app = $this->j->approved();
    $outsider = $this->userWith([Permission::ConfigRead]);
    $this->login($this->j->rm);
    $note = $this->api('POST', "/api/v1/applications/{$app['id']}/notes", ['body' => 'Customer will bring audited accounts on Friday.', 'visibility' => 'internal', 'mention_user_ids' => [$this->j->analyst->id]])->assertCreated()->json('data');
    expect($note['mentions'][0]['id'])->toBe($this->j->analyst->id)
        ->and(DB::table('communications')->where('event', 'note.mention')->where('recipient_user_id', $this->j->analyst->id)->value('subject'))->toContain('mentioned you on '.$app['reference']);
    $this->api('POST', "/api/v1/applications/{$app['id']}/notes", ['body' => 'FYI', 'visibility' => 'internal', 'mention_user_ids' => [$outsider->id]])->assertStatus(422);
    $this->api('POST', "/api/v1/applications/{$app['id']}/notes", ['body' => 'Your offer is being prepared.', 'visibility' => 'customer'])->assertCreated();
    $this->login($this->j->analyst);
    $this->api('POST', "/api/v1/applications/{$app['id']}/notes", ['body' => 'Your offer is being prepared.', 'visibility' => 'customer'])->assertForbidden();
    expect($this->api('GET', "/api/v1/applications/{$app['id']}/notes")->assertOk()->json('data'))->toHaveCount(2);
    expect(fn () => DB::transaction(fn () => DB::table('case_notes')->where('id', $note['id'])->update(['body' => 'edited'])))->toThrow(QueryException::class);
})->group('FR-WFL-010');

it('lists who could take a task and who can be mentioned', function () {
    $app = $this->j->approved();
    $task = DB::table('workflow_tasks')->where('application_id', $app['id'])->where('status', 'open')->first();
    $this->login($this->supervisor);
    $people = collect($this->api('GET', "/api/v1/tasks/{$task->id}/candidates")->assertOk()->json('data'))->pluck('id')->all();
    expect($people)->toContain($this->j->ops->id, $this->ops2->id)->not->toContain($this->j->rm->id);
    $this->login($this->j->ops);
    $this->api('GET', "/api/v1/tasks/{$task->id}/candidates")->assertForbidden();
    expect(collect($this->api('GET', "/api/v1/applications/{$app['id']}/mentionable")->assertOk()->json('data'))->pluck('id')->all())->toContain($this->j->analyst->id, $this->j->rm->id);
})->group('FR-WFL-005', 'FR-WFL-010');
