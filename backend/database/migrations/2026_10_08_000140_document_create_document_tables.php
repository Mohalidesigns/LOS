<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M07 Documents (FR-DOC-001..009). `document_versions` is immutable evidence:
 * SHA-256 computed before storage, scan verdict, storage key; an infected
 * version has no storage key and can never be read. `checklist_items` is the
 * per-application checklist derived from the pinned product version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('code', 48);
            $table->string('name', 200);
            $table->unsignedSmallInteger('position');
            $table->boolean('mandatory');
            $table->string('status', 16);
            $table->string('waiver_authority', 48);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->uuid('document_id')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->uuid('waiver_change_request_id')->nullable();
            $table->string('verified_by', 64)->nullable();
            $table->timestampTz('verified_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'code']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'valid_until']);
        });
        DB::statement("alter table checklist_items add constraint checklist_items_status_chk check (status in ('not_received', 'received', 'under_review', 'verified', 'rejected', 'waived', 'expired'))");

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('checklist_item_id')->nullable();
            $table->uuid('party_id')->nullable();
            $table->string('document_type', 48);
            $table->string('title', 200);
            $table->uuid('created_by');
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'checklist_item_id'])->references(['tenant_id', 'id'])->on('checklist_items');
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('document_id');
            $table->unsignedInteger('version_no');
            $table->string('filename', 255);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 16);
            $table->string('scan_signature', 200)->nullable();
            $table->string('scanner', 64);
            $table->string('storage_key', 255)->nullable();
            $table->unsignedSmallInteger('key_version')->nullable();
            $table->string('uploaded_by', 64);
            $table->timestampTz('uploaded_at', 6);
            $table->foreign(['tenant_id', 'document_id'])->references(['tenant_id', 'id'])->on('documents');
            $table->unique(['document_id', 'version_no']);
            $table->index(['tenant_id', 'sha256']);
        });
        DB::statement("alter table document_versions add constraint document_versions_scan_chk check (scan_status in ('clean', 'infected'))");
        // FR-DOC-004 enforced by the database too: only a clean version may point at stored bytes.
        DB::statement("alter table document_versions add constraint document_versions_quarantine_chk check (scan_status = 'clean' or storage_key is null)");

        $pg = $this->pg();
        foreach (['checklist_items', 'documents'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
        $pg->grantAppendOnly('document_versions');
        $pg->tenantRls('document_versions');
        $pg->immutable('document_versions');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('checklist_items');
    }
};
