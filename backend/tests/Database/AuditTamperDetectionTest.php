<?php

declare(strict_types=1);

use Fundly\Shared\Audit\AuditVerifier;
use Fundly\Shared\Audit\HashChain;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * D-021: prevent tampering for application and owner roles (trigger + grants),
 * detect it for anyone powerful enough to disable the trigger.
 */
beforeEach(function () {
    $this->t = $this->provisionTenant('tamper');
    expect(Artisan::call('audit:checkpoint'))->toBe(0);
    $this->useTenant($this->t);
    $this->owner = DB::connection('pgsql_owner');
    $this->owner->statement("select set_config('app.tenant_id', ?, false)", [$this->t->id]);
});

it('rejects UPDATE, DELETE and TRUNCATE on audit events even for the schema owner', function () {
    $id = DB::table('audit_events')->orderBy('seq')->value('id');
    expect(fn () => $this->owner->update('update audit_events set action = ? where id = ?', ['x', $id]))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => $this->owner->delete('delete from audit_events where id = ?', [$id]))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => $this->owner->statement('truncate audit_events'))->toThrow(QueryException::class, 'append-only');
})->group('FR-AUD-003');

it('detects an edited event after the trigger is disabled by the owner', function () {
    expect(app(AuditVerifier::class)->verify($this->t->id)->ok())->toBeTrue();
    $victim = DB::table('audit_events')->where('seq', 3)->first();

    $this->owner->statement('alter table audit_events disable trigger audit_events_immutable');
    $this->owner->update("update audit_events set after = '{\"tampered\": true}'::jsonb where id = ?", [$victim->id]);
    $this->owner->statement('alter table audit_events enable trigger audit_events_immutable');

    $result = app(AuditVerifier::class)->verify($this->t->id);
    expect($result->ok())->toBeFalse()
        ->and($result->breaks()[0])->toMatchArray(['seq' => 3, 'kind' => 'content']);
    expect(Artisan::call('audit:verify', ['--tenant' => $this->t->id]))->toBe(1)
        ->and(Artisan::output())->toContain('FAIL')->toContain('seq 3 [content]');
})->group('FR-AUD-004', 'FR-AUD-003');

it('detects a deleted event as a sequence gap', function () {
    $this->owner->statement('alter table audit_events disable trigger audit_events_immutable');
    $this->owner->delete('delete from audit_events where tenant_id = ? and seq = 4', [$this->t->id]);
    $this->owner->statement('alter table audit_events enable trigger audit_events_immutable');
    $kinds = array_column(app(AuditVerifier::class)->verify($this->t->id)->breaks(), 'kind');
    expect($kinds)->toContain('missing')->toContain('link');
})->group('FR-AUD-004');

it('detects a fully re-hashed (rewritten) chain through the anchored checkpoint', function () {
    $this->owner->statement('alter table audit_events disable trigger audit_events_immutable');
    $rows = $this->owner->table('audit_events')->where('tenant_id', $this->t->id)->orderBy('seq')->get();
    $prev = HashChain::GENESIS;
    foreach ($rows as $r) {
        $data = AuditVerifier::hydrate((array) $r);
        if ((int) $r->seq === 2) {
            $data['reason_text'] = 'rewritten history';
        }
        $data['prev_hash'] = $prev;
        $hash = HashChain::compute($data);
        $this->owner->update('update audit_events set reason_text = ?, prev_hash = ?, hash = ? where id = ?', [$data['reason_text'], $prev, $hash, $r->id]);
        $prev = $hash;
    }
    $this->owner->statement('alter table audit_events enable trigger audit_events_immutable');

    $result = app(AuditVerifier::class)->verify($this->t->id);
    expect(array_column($result->breaks(), 'kind'))->toBe(['checkpoint']);
})->group('FR-AUD-004');
