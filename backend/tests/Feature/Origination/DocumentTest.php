<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\LendingFixtures;

const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

beforeEach(function () {
    Storage::fake('documents');
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage, Permission::DocumentUpload]);
    $this->docOfficer = $this->userWith([Permission::ApplicationView, Permission::DocumentUpload, Permission::DocumentVerify, Permission::ChangeRequestRead]);
    $this->approver = $this->userWith([Permission::ApplicationView, Permission::DocumentWaiveApprove, Permission::ChangeRequestRead]);
    $this->login($this->rm);
    $company = LendingFixtures::company($this, orgUnitId: $this->org['lagos']['id']);
    $this->loan = $this->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $this->org['le']['id'], 'org_unit_id' => $this->org['lagos']['id'], 'product_key' => 'sme-term-loan',
        'primary_party_id' => $company['id'], 'requested_amount' => '12500000.00', 'tenor_months' => 24, 'purpose' => 'Equipment',
    ])->assertCreated()->json('data');
});

function pdf(string $marker = 'statement'): UploadedFile
{
    return UploadedFile::fake()->createWithContent("{$marker}.pdf", "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% {$marker}\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

function item(object $t, string $code): array
{
    return collect($t->api('GET', "/api/v1/applications/{$t->loan['id']}/checklist")->assertOk()->json('data.items'))->firstWhere('code', $code);
}

function upload(object $t, UploadedFile $file, ?string $itemId = null): TestResponse
{
    return $t->api('POST', "/api/v1/applications/{$t->loan['id']}/documents", ['document_type' => 'supporting', 'checklist_item_id' => $itemId, 'file' => $file]);
}

it('derives the checklist from the pinned product: applicant type and amount band decide the items', function () {
    $checklist = $this->api('GET', "/api/v1/applications/{$this->loan['id']}/checklist")->assertOk()->json('data');
    expect(collect($checklist['items'])->pluck('status', 'code')->all())->toBe(['CAC_CERT' => 'not_received', 'STATEMENT_6M' => 'not_received', 'AUDITED_FS' => 'not_received'])
        ->and($checklist['summary'])->toMatchArray(['mandatory_total' => 3, 'mandatory_satisfied' => 0, 'complete' => false]);
})->group('FR-DOC-007', 'FR-PRD-004');

it('hashes, scans, encrypts and versions a clean upload; the content round-trips and every read is audited', function () {
    $cac = item($this, 'CAC_CERT');
    $file = pdf('cac');
    $bytes = (string) file_get_contents($file->getRealPath());
    $doc = upload($this, $file, $cac['id'])->assertCreated()->json('data');
    $v = $doc['latest_version'];
    expect($v)->toMatchArray(['version_no' => 1, 'mime_type' => 'application/pdf', 'scan_status' => 'clean', 'scan_signature' => null, 'sha256' => hash('sha256', $bytes), 'duplicates' => []]);
    expect(item($this, 'CAC_CERT')['status'])->toBe('received');

    // encrypted at rest: the stored object is not the plaintext
    $key = DB::table('document_versions')->where('id', $v['id'])->value('storage_key');
    expect(Storage::disk('documents')->get($key))->not->toContain('%PDF');
    $res = $this->get("http://{$this->tenant->host}/api/v1/documents/{$doc['id']}/versions/{$v['id']}/content", ['Referer' => 'http://localhost/'])->assertOk();
    expect($res->getContent())->toBe($bytes)->and($res->headers->get('Digest'))->toBe('sha-256='.base64_encode(hash('sha256', $bytes, true)));
    expect(DB::table('audit_events')->where('action', 'document.content.read')->where('entity_id', $doc['id'])->exists())->toBeTrue();

    // a second upload to the same item is version 2 and resets review
    $doc2 = upload($this, pdf('cac-v2'), $cac['id'])->assertCreated()->json('data');
    expect($doc2['id'])->toBe($doc['id'])->and($doc2['versions'])->toHaveCount(2)->and($doc2['latest_version']['version_no'])->toBe(2);
    expect(fn () => DB::transaction(fn () => DB::table('document_versions')->where('document_id', $doc['id'])->update(['sha256' => str_repeat('0', 64)])))->toThrow(QueryException::class);
})->group('FR-DOC-001', 'FR-DOC-005');

it('quarantines an infected file: never stored, never readable, the checklist item stays open (demo step 8)', function () {
    $st = item($this, 'STATEMENT_6M');
    $doc = upload($this, UploadedFile::fake()->createWithContent('eicar.txt', EICAR), $st['id'])->assertCreated()->json('data');
    expect($doc['latest_version'])->toMatchArray(['scan_status' => 'infected', 'scan_signature' => 'Eicar-Test-Signature', 'sha256' => hash('sha256', EICAR)]);
    expect(DB::table('document_versions')->where('id', $doc['latest_version']['id'])->value('storage_key'))->toBeNull()
        ->and(Storage::disk('documents')->allFiles())->toBe([])
        ->and(item($this, 'STATEMENT_6M')['status'])->toBe('not_received');
    $this->get("http://{$this->tenant->host}/api/v1/documents/{$doc['id']}/versions/{$doc['latest_version']['id']}/content", ['Referer' => 'http://localhost/', 'Accept' => 'application/json'])
        ->assertStatus(423)->assertJsonPath('code', 'document-quarantined');
    expect(DB::table('audit_events')->where('action', 'document.quarantined')->exists())->toBeTrue();
    // the database refuses a storage key on an infected version
    expect(fn () => DB::transaction(fn () => DB::table('document_versions')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'document_id' => $doc['id'], 'version_no' => 9, 'filename' => 'x', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'scan_status' => 'infected', 'scanner' => 's', 'storage_key' => 'k', 'uploaded_by' => 'u', 'uploaded_at' => now()])))->toThrow(QueryException::class);
})->group('FR-DOC-004');

it('enforces the format and size policy on sniffed content, not the client name', function () {
    upload($this, UploadedFile::fake()->createWithContent('statement.pdf', "\x7fELF\x02\x01\x01\0binary"))->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);
    config(['fundly.documents.max_bytes' => 64]);
    upload($this, pdf(str_repeat('x', 100)))->assertStatus(422);
})->group('FR-DOC-002');

it('flags the same file submitted on another application', function () {
    $first = upload($this, pdf('shared'))->assertCreated()->json('data');
    $company2 = LendingFixtures::company($this, 'Second Co Limited', 'RC7770001', $this->org['lagos']['id']);
    $other = $this->api('POST', '/api/v1/applications', ['legal_entity_id' => $this->org['le']['id'], 'org_unit_id' => $this->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $company2['id'], 'requested_amount' => '1000000.00', 'tenor_months' => 12, 'purpose' => 'Stock'])->json('data');
    $this->loan = $other;
    $dupe = upload($this, pdf('shared'))->assertCreated()->json('data.latest_version.duplicates');
    expect($dupe)->toHaveCount(1)->and($dupe[0])->toMatchArray(['document_id' => $first['id'], 'same_application' => false])->and($dupe[0]['application_reference'])->toEndWith('-000001');
})->group('FR-DOC-006');

it('verifies with an expiry date, rejects with a reason, and expires stale documents', function () {
    $cac = item($this, 'CAC_CERT');
    upload($this, pdf('cac'), $cac['id'])->assertCreated();
    $this->login($this->docOfficer);
    $this->api('POST', "/api/v1/checklist-items/{$cac['id']}/actions/reject", [])->assertStatus(422);
    $this->api('POST', "/api/v1/checklist-items/{$cac['id']}/actions/reject", ['reason' => 'Unreadable scan, please re-upload'])->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->api('POST', "/api/v1/checklist-items/{$cac['id']}/actions/verify")->assertStatus(422); // nothing received
    $this->login($this->rm);
    upload($this, pdf('cac-better'), $cac['id'])->assertCreated();
    $this->login($this->docOfficer);
    $ok = $this->api('POST', "/api/v1/checklist-items/{$cac['id']}/actions/verify", ['valid_until' => now()->addMonths(6)->format('Y-m-d'), 'note' => 'Matches CAC search'])->assertOk()->json('data');
    expect($ok)->toMatchArray(['status' => 'verified', 'verified_by' => $this->docOfficer->id, 'valid_until' => now()->addMonths(6)->format('Y-m-d')]);

    DB::table('checklist_items')->where('id', $cac['id'])->update(['valid_until' => now()->subDay()->format('Y-m-d')]);
    expect(Artisan::call('documents:expire'))->toBe(0);
    expect(item($this, 'CAC_CERT')['status'])->toBe('expired');
})->group('FR-DOC-007', 'FR-DOC-009');

it('waives only through the configured authority (maker-checker) and moves Documentation → Assessment when complete', function () {
    // bring the application to Documentation (KYC is covered by KycFlowTest)
    $etag = $this->api('GET', "/api/v1/applications/{$this->loan['id']}")->headers->get('ETag');
    $this->api('POST', "/api/v1/applications/{$this->loan['id']}/actions/submit", [], ['If-Match' => $etag])->assertOk();
    app(ApplicationLifecycle::class)->advance($this->loan['id'], CanonicalStatus::Documentation, 'CDD_COMPLETE_SCREENING_CLEAR', null, Principal::system($this->tenant->id, SystemIdentity::Workflow));

    foreach (['CAC_CERT', 'STATEMENT_6M'] as $code) {
        $this->login($this->rm);
        upload($this, pdf(strtolower($code)), item($this, $code)['id'])->assertCreated();
        $this->login($this->docOfficer);
        $this->api('POST', '/api/v1/checklist-items/'.item($this, $code)['id'].'/actions/verify')->assertOk();
    }
    $afs = item($this, 'AUDITED_FS');
    $this->api('POST', "/api/v1/checklist-items/{$afs['id']}/actions/waive", ['reason' => 'too short'])->assertStatus(422);
    $cr = $this->api('POST', "/api/v1/checklist-items/{$afs['id']}/actions/waive", ['reason' => 'Company is 2 years old; management accounts accepted instead'])->assertStatus(202)->json('data');
    expect(item($this, 'AUDITED_FS'))->toMatchArray(['status' => 'not_received', 'waiver_change_request_id' => $cr['id']]);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden(); // the maker cannot approve
    expect($this->api('GET', "/api/v1/applications/{$this->loan['id']}")->json('data.status'))->toBe('documentation');

    $this->login($this->approver);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk();
    expect(item($this, 'AUDITED_FS')['status'])->toBe('waived');
    expect($this->api('GET', "/api/v1/applications/{$this->loan['id']}/checklist")->json('data.summary'))->toMatchArray(['mandatory_satisfied' => 3, 'complete' => true]);
    expect($this->api('GET', "/api/v1/applications/{$this->loan['id']}")->json('data.status'))->toBe('assessment');
    $last = collect($this->api('GET', "/api/v1/applications/{$this->loan['id']}/timeline")->json('data'))->last();
    expect($last['payload'])->toMatchArray(['to' => 'assessment', 'reason_code' => 'CHECKLIST_COMPLETE'])->and($last['actor']['id'])->toBe('system:workflow');
})->group('FR-DOC-008', 'FR-SEC-007', 'LOS-FR-282');

it('denies uploads and reviews without the document permissions and outside scope', function () {
    $viewer = $this->userWith([Permission::ApplicationView]);
    $this->login($viewer);
    upload($this, pdf())->assertForbidden();
    $this->api('POST', '/api/v1/checklist-items/'.item($this, 'CAC_CERT')['id'].'/actions/verify')->assertForbidden();
    $kanoOfficer = $this->userWith([Permission::ApplicationView, Permission::DocumentUpload, Permission::DocumentVerify], ['org_unit_id' => $this->org['kano']['id']]);
    $this->login($kanoOfficer);
    upload($this, pdf())->assertForbidden();
    $this->api('GET', "/api/v1/applications/{$this->loan['id']}/checklist")->assertForbidden();
})->group('FR-SEC-003');
