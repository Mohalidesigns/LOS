<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\ActorType;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

/** The auditor template: every read permission, unrestricted scope, nothing operational (LOS-FR-279). */
function auditor(object $t): object
{
    $read = array_values(array_filter(Permission::cases(), static fn (Permission $p): bool => $p->isReadOnly()));

    return $t->userWith($read);
}

it('logs who read application and PII data, with the declared purpose, append-only (FR-AUD-006)', function () {
    $j = Journey::setUp($this);
    $app = $j->approved();
    $partyId = $app['applicants'][0]['party_id'] ?? DB::table('application_applicants')->where('application_id', $app['id'])->value('party_id');
    $this->login($j->rm);
    $this->api('GET', "/api/v1/applications/{$app['id']}", [], ['X-Access-Purpose' => 'case_review'])->assertOk();
    $this->api('GET', "/api/v1/parties/{$partyId}")->assertOk();
    $this->api('GET', "/api/v1/applications/{$app['id']}", [], ['X-Access-Purpose' => 'Not A Code!'])->assertStatus(422);
    // a refused read is not access
    $this->api('GET', '/api/v1/audit/read-access')->assertForbidden();

    $this->login(auditor($this));
    $rows = $this->api('GET', "/api/v1/audit/read-access?filter[resource_id]={$app['id']}&filter[actor_id]={$j->rm->id}&filter[purpose]=case_review")->assertOk()->json('data');
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['resource_type' => 'application', 'route' => 'applications.show', 'contains_pii' => true, 'purpose' => 'case_review', 'actor_type' => 'user']);
    $party = $this->api('GET', "/api/v1/audit/read-access?filter[resource_type]=party&filter[resource_id]={$partyId}&filter[pii]=true")->assertOk()->json('data');
    expect(collect($party)->pluck('actor_id')->all())->toContain($j->rm->id);
    // the 422 left no row; the log cannot be edited
    expect(DB::table('pii_access_log')->where('resource_id', $app['id'])->where('actor_id', $j->rm->id)->where('route', 'applications.show')->whereNull('purpose')->exists())->toBeTrue()
        ->and(fn () => DB::transaction(fn () => DB::table('pii_access_log')->update(['purpose' => 'x'])))->toThrow(QueryException::class);
})->group('FR-AUD-006');

it('logs every integration call, email included, PII-masked, readable by auditors (FR-AUD-008)', function () {
    $j = Journey::setUp($this);
    $j->approved();
    LendingFixtures::drainOutbox($this);
    $this->login(auditor($this));
    $calls = collect($this->api('GET', '/api/v1/integration-calls?page[size]=100')->assertOk()->json('data'));
    expect($calls->pluck('port')->unique()->all())->toContain('credit_bureau', 'identity_verification', 'screening', 'email');
    $email = DB::table('integration_calls')->where('port', 'email')->first();
    expect($email->outcome)->toBe('success')->and($email->adapter_key)->toBe('smtp')
        ->and((string) $email->request)->not->toContain('amaka.nwosu@example.ng');
    // raw identifiers never reach the log
    expect(DB::table('integration_calls')->whereRaw('request::text like ?', ['%22255566677%'])->exists())->toBeFalse();
})->group('FR-AUD-008');

it('gives auditors a filterable explorer and the application state as at any instant, without operational rights (FR-AUD-009, FR-AUD-011)', function () {
    $j = Journey::setUp($this);
    $created = now()->toIso8601String();
    $app = $j->approved();
    $auditor = auditor($this);
    $this->login($auditor);

    $events = collect($this->api('GET', "/api/v1/audit-events?filter[application_id]={$app['id']}&page[size]=100")->assertOk()->json('data'));
    expect($events->pluck('action')->all())->toContain('application.created', 'application.status_changed', 'approval.vote.cast');
    $prefixed = collect($this->api('GET', "/api/v1/audit-events?filter[action]=application.*&filter[actor_id]={$j->rm->id}")->assertOk()->json('data'));
    expect($prefixed)->not->toBeEmpty()->and($prefixed->every(fn ($e) => str_starts_with($e['action'], 'application.')))->toBeTrue();
    expect($this->api('GET', '/api/v1/audit-events?filter[actor_type]=system&page[size]=5')->assertOk()->json('data.0.actor_type'))->toBe('system');
    $this->api('GET', '/api/v1/audit-events?filter[from]=yesterday-ish-not-a-date')->assertStatus(422);

    // as-at: replay to an instant
    $then = $this->api('GET', "/api/v1/applications/{$app['id']}/as-at?t=".urlencode(now()->toIso8601String()))->assertOk()->json('data.state');
    expect($then['status'])->toBe('approved');
    $before = $this->api('GET', "/api/v1/applications/{$app['id']}/as-at?t=".urlencode($created))->assertOk()->json('data.state');
    expect($before)->toBeNull();

    // read-only: an auditor cannot act on the case
    $etag = (string) $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $this->api('POST', "/api/v1/applications/{$app['id']}/actions/hold", ['reason' => 'x'], ['If-Match' => $etag])->assertForbidden();
})->group('FR-AUD-009', 'FR-AUD-011');

it('exports an evidence pack whose manifest hashes every file, and audits the export (FR-AUD-010)', function () {
    $j = Journey::setUp($this);
    $app = $j->approved();
    $j->issueOffer($app);
    $this->login($j->rm);
    $this->api('GET', "/api/v1/applications/{$app['id']}/evidence-pack")->assertForbidden();

    $this->login(auditor($this));
    $this->validateOpenApi = false; // binary body; the operation is documented
    $res = $this->api('GET', "/api/v1/applications/{$app['id']}/evidence-pack", [], ['X-Access-Purpose' => 'regulatory_examination'])->assertOk();
    $this->validateOpenApi = true;
    expect($res->headers->get('Content-Type'))->toBe('application/zip');
    $tmp = tempnam(sys_get_temp_dir(), 'evt');
    file_put_contents($tmp, $res->getContent());
    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    $manifestJson = (string) $zip->getFromName('manifest.json');
    $manifest = json_decode($manifestJson, true);
    expect(hash('sha256', $manifestJson))->toBe($res->headers->get('X-Manifest-SHA256'))
        ->and($manifest)->toMatchArray(['format' => 'fundly.evidence-pack/1', 'signature' => null, 'hash_algorithm' => 'sha256'])
        ->and($manifest['application']['id'])->toBe($app['id']);
    $paths = collect($manifest['files'])->pluck('path');
    expect($paths->all())->toContain('application.json', 'timeline.json', 'documents.json', 'decisions.json', 'approvals.json', 'screening.json', 'communications.json', 'offers.json')
        ->and($paths->filter(fn ($p) => str_starts_with($p, 'documents/'))->count())->toBeGreaterThan(0);
    foreach ($manifest['files'] as $f) {
        $bytes = (string) $zip->getFromName($f['path']);
        expect(hash('sha256', $bytes))->toBe($f['sha256'])->and(strlen($bytes))->toBe($f['bytes']);
    }
    expect(collect(json_decode((string) $zip->getFromName('timeline.json'), true))->pluck('type')->all())->toContain('application.created');
    $zip->close();
    unlink($tmp);

    $audit = DB::table('audit_events')->where('action', 'audit.evidence_pack.exported')->where('entity_id', $app['id'])->first();
    expect(json_decode((string) $audit->after, true)['manifest_sha256'])->toBe($manifest ? hash('sha256', $manifestJson) : null);
    expect(DB::table('pii_access_log')->where('route', 'applications.evidence-pack')->where('purpose', 'regulatory_examination')->exists())->toBeTrue();
})->group('FR-AUD-010');

it('reports overrides, waivers and manual interventions over a period, by type and by user (FR-AUD-016)', function () {
    $this->provisionTenant();
    $maker = $this->userWith([Permission::ApplicationView]);
    $checker = $this->userWith([Permission::ApplicationView]);
    $analyst = $this->userWith([Permission::ApplicationView]);
    $as = static fn (object $u): Actor => new Actor(ActorType::User, $u->id, ['test']);
    $trail = app(AuditTrail::class);
    $appId = '01900000-0000-7000-8000-000000000001';
    DB::transaction(function () use ($trail, $as, $maker, $checker, $analyst, $appId): void {
        $trail->record(new AuditEntry(action: 'change_request.submitted', entityType: 'change_request', entityId: 'cr-1', after: ['action_type' => 'condition.waive'], reasonText: 'Group policy in force'), $as($maker));
        $trail->record(new AuditEntry(action: 'condition.waived', entityType: 'condition', entityId: 'c-1', after: ['status' => 'waived', 'change_request_id' => 'cr-1'], reasonText: 'Group policy in force'), $as($checker));
        $trail->record(new AuditEntry(action: 'credit.exception.raised', entityType: 'decision', entityId: 'd-1', after: ['severity' => 'medium'], reasonText: 'Enquiries explained'), $as($analyst));
        $trail->record(new AuditEntry(action: 'workflow.task_reassigned', entityType: 'application', entityId: $appId, after: ['task_id' => 't-1'], reasonText: 'Cover for leave'), $as($checker));
        $trail->record(new AuditEntry(action: 'application.amended', entityType: 'application', entityId: $appId), $as($maker)); // not an override
    });

    $this->login(auditor($this));
    $report = $this->api('GET', '/api/v1/audit/overrides')->assertOk()->json();
    expect($report['data'])->toHaveCount(3)
        ->and($report['meta']['summary']['by_type'])->toBe(['policy_exception' => 1, 'waiver' => 1, 'manual_intervention' => 1])
        ->and($report['meta']['summary']['by_user'][0])->toMatchArray(['count' => 2])
        ->and($report['meta']['summary']['by_user'][0]['user']['id'])->toBe($checker->id);
    $waiver = collect($report['data'])->firstWhere('type', 'waiver');
    expect($waiver)->toMatchArray(['action' => 'condition.waived', 'change_request_id' => 'cr-1', 'reason' => 'Group policy in force'])
        ->and($waiver['performed_by']['id'])->toBe($checker->id)
        ->and($waiver['requested_by']['id'])->toBe($maker->id);
    expect(collect($report['data'])->firstWhere('type', 'manual_intervention')['application_id'])->toBe($appId);

    expect($this->api('GET', '/api/v1/audit/overrides?filter[type]=policy_exception')->assertOk()->json('data.0.performed_by.id'))->toBe($analyst->id);
    expect($this->api('GET', "/api/v1/audit/overrides?filter[actor_id]={$checker->id}")->assertOk()->json('data'))->toHaveCount(2);
    expect($this->api('GET', '/api/v1/audit/overrides?filter[from]='.urlencode(now()->addDay()->toIso8601String()))->assertOk()->json('data'))->toBe([]);
    $this->api('GET', '/api/v1/audit/overrides?filter[type]=bogus')->assertStatus(422);
})->group('FR-AUD-016');
