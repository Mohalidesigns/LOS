<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigActivateRequest]);
    $this->reviewer = $this->userWith([Permission::ConfigRead, Permission::ConfigReview, Permission::ConfigActivateApprove, Permission::ChangeRequestRead]);
    $this->base = '/api/v1/config-artifacts/security.session_policy';
});

function draftAndApprove(object $t, array $content): array
{
    $t->login($t->author);
    $artifact = $t->artifact ??= $t->api('POST', $t->base, ['key' => 'default', 'name' => 'Session policy'])->assertCreated()->json('data');
    $v = $t->api('POST', "{$t->base}/{$artifact['id']}/versions", ['content' => $content])->assertCreated()->json('data');
    $t->api('POST', "{$t->base}/{$artifact['id']}/versions/{$v['id']}/actions/submit")->assertOk()->assertJsonPath('data.status', 'in_review');
    $t->login($t->reviewer);
    $t->api('POST', "{$t->base}/{$artifact['id']}/versions/{$v['id']}/actions/approve")->assertOk()->assertJsonPath('data.status', 'approved');

    return [$artifact, $v];
}

function activate(object $t, array $artifact, array $v, string $action = 'activate'): array
{
    $t->login($t->author);
    $cr = $t->api('POST', "{$t->base}/{$artifact['id']}/versions/{$v['id']}/actions/{$action}")->assertStatus(202)->json('data');
    $t->login($t->reviewer);

    return $t->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk()->json('data');
}

it('runs draft → in_review → approved → active with maker-checker activation', function () {
    [$artifact, $v] = draftAndApprove($this, ['idle_minutes' => 10, 'absolute_minutes' => 240, 'max_concurrent' => 2]);
    expect($this->api('GET', "{$this->base}/{$artifact['id']}")->json('data.active_version_id'))->toBeNull();
    $done = activate($this, $artifact, $v);
    expect($done['execution_result']['active_version_id'])->toBe($v['id']);
    $this->api('GET', "{$this->base}/{$artifact['id']}/versions/{$v['id']}")->assertJsonPath('data.status', 'active')->assertJsonPath('data.activated_by', $this->reviewer->id);
})->group('FR-TEN-009', 'FR-SEC-007');

it('validates content per type and only lets drafts be edited (with If-Match)', function () {
    $this->login($this->author);
    $artifact = $this->api('POST', $this->base, ['key' => 'default', 'name' => 'Session policy'])->json('data');
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions", ['content' => ['idle_minutes' => 1]])->assertStatus(422)->assertJsonStructure(['errors' => ['content.idle_minutes', 'content.absolute_minutes']]);
    $v = $this->api('POST', "{$this->base}/{$artifact['id']}/versions", ['content' => ['idle_minutes' => 10, 'absolute_minutes' => 240, 'max_concurrent' => 1]]);
    $this->api('PATCH', "{$this->base}/{$artifact['id']}/versions/{$v->json('data.id')}", ['content' => ['idle_minutes' => 20, 'absolute_minutes' => 240, 'max_concurrent' => 1]], ['If-Match' => $v->headers->get('ETag')])
        ->assertOk()->assertJsonPath('data.content.idle_minutes', 20);
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v->json('data.id')}/actions/submit")->assertOk();
    $this->api('PATCH', "{$this->base}/{$artifact['id']}/versions/{$v->json('data.id')}", ['content' => ['idle_minutes' => 30, 'absolute_minutes' => 240, 'max_concurrent' => 1]], ['If-Match' => '*'])
        ->assertStatus(409)->assertJsonPath('code', 'config-version-immutable');
    $this->api('GET', '/api/v1/config-artifacts/unknown.type')->assertNotFound();
})->group('FR-TEN-009');

it('makes approved content immutable at the database, even for direct SQL', function () {
    [$artifact, $v] = draftAndApprove($this, ['idle_minutes' => 10, 'absolute_minutes' => 240, 'max_concurrent' => 1]);
    // each attempt runs in a savepoint so the failure does not abort the test transaction
    expect(fn () => DB::transaction(fn () => DB::table('config_versions')->where('id', $v['id'])->update(['content' => json_encode(['idle_minutes' => 999])])))
        ->toThrow(QueryException::class, 'immutable');
    expect(fn () => DB::transaction(fn () => DB::table('config_versions')->where('id', $v['id'])->delete()))->toThrow(QueryException::class, 'only drafts can be deleted');
    // status transitions remain possible (that is how activation works)
    expect(DB::table('config_versions')->where('id', $v['id'])->value('status'))->toBe('approved');
})->group('FR-TEN-009', 'FR-AUD-003');

it('requires a reviewer other than the author, and an activation checker other than the author', function () {
    $both = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigReview, Permission::ConfigActivateRequest, Permission::ConfigActivateApprove, Permission::ChangeRequestRead]);
    $this->login($both);
    $artifact = $this->api('POST', $this->base, ['key' => 'default', 'name' => 'Session policy'])->json('data');
    $v = $this->api('POST', "{$this->base}/{$artifact['id']}/versions", ['content' => ['idle_minutes' => 10, 'absolute_minutes' => 240, 'max_concurrent' => 1]])->json('data');
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v['id']}/actions/submit")->assertOk();
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v['id']}/actions/approve")->assertForbidden()->assertJsonPath('code', 'sod-conflict');

    $this->login($this->reviewer);
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v['id']}/actions/approve")->assertOk();
    $this->login($this->author);
    $cr = $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v['id']}/actions/activate")->json('data');
    // the author of the content is excluded as checker even though they are not the maker
    $this->login($both);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden();
})->group('FR-TEN-009', 'FR-SEC-007');

it('activates a new version, supersedes the old one, and rolls back via maker-checker', function () {
    [$artifact, $v1] = draftAndApprove($this, ['idle_minutes' => 10, 'absolute_minutes' => 240, 'max_concurrent' => 1]);
    activate($this, $artifact, $v1);
    [, $v2] = draftAndApprove($this, ['idle_minutes' => 30, 'absolute_minutes' => 600, 'max_concurrent' => 3]);
    expect($v2['version_no'])->toBe(2);
    activate($this, $artifact, $v2);
    $this->api('GET', "{$this->base}/{$artifact['id']}/versions/{$v1['id']}")->assertJsonPath('data.status', 'superseded');

    // rollback only applies to previously active versions
    $this->login($this->author);
    $this->api('POST', "{$this->base}/{$artifact['id']}/versions/{$v2['id']}/actions/rollback")->assertStatus(422);
    $done = activate($this, $artifact, $v1, 'rollback');
    expect($done['execution_result']['rollback'])->toBeTrue()->and($done['execution_result']['active_version_id'])->toBe($v1['id']);
    $versions = collect($this->api('GET', "{$this->base}/{$artifact['id']}/versions")->json('data'))->pluck('status', 'version_no')->all();
    expect($versions)->toBe([2 => 'superseded', 1 => 'active']);
    expect(DB::table('audit_events')->where('action', 'platform.config.rolled_back')->exists())->toBeTrue();
})->group('FR-TEN-009');

it('applies the active session policy at runtime (config live without restart)', function () {
    [$artifact, $v] = draftAndApprove($this, ['idle_minutes' => 5, 'absolute_minutes' => 240, 'max_concurrent' => 1]);
    activate($this, $artifact, $v);
    $this->login($this->author);
    $this->travel(6)->minutes();
    $this->api('GET', '/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'session-expired');
})->group('FR-TEN-009', 'FR-SEC-015');
