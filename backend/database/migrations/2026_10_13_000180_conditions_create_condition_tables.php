<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M13 Conditions: CP/CS items with owner, due date, evidence and sign-off,
 * and the final pre-disbursement checks (FR-CPR-001..005). Checks are
 * evidence and append-only; conditions change status but are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('kind', 16);
            $table->string('source', 16);
            $table->uuid('source_ref')->nullable();
            $table->text('description');
            $table->boolean('mandatory');
            $table->uuid('owner_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 16);
            $table->uuid('evidence_document_id')->nullable();
            $table->uuid('evidence_version_id')->nullable();
            $table->string('evidence_reference', 200)->nullable();
            $table->text('signoff_note')->nullable();
            $table->string('signed_off_by', 64)->nullable();
            $table->timestampTz('signed_off_at', 6)->nullable();
            $table->text('waiver_reason')->nullable();
            $table->uuid('waiver_change_request_id')->nullable();
            $table->string('created_by', 64);
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table conditions add constraint conditions_kind_chk check (kind in ('precedent', 'subsequent'))");
        DB::statement("alter table conditions add constraint conditions_source_chk check (source in ('approval', 'manual'))");
        DB::statement("alter table conditions add constraint conditions_status_chk check (status in ('open', 'waiver_pending', 'satisfied', 'waived'))");
        DB::statement("alter table conditions add constraint conditions_evidence_chk check (status <> 'satisfied' or evidence_version_id is not null or evidence_reference is not null)");
        // an approval condition is imported once
        DB::statement('create unique index conditions_source_once on conditions (application_id, source_ref) where source_ref is not null');

        Schema::create('pre_disbursement_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('sequence');
            $table->boolean('passed');
            $table->jsonb('items');
            $table->string('bank_code', 8);
            $table->text('account_no_enc');
            $table->string('account_no_masked', 16);
            $table->string('account_name', 200)->nullable();
            $table->unsignedSmallInteger('name_match_score')->nullable();
            $table->jsonb('screening');
            $table->string('checked_by', 64);
            $table->timestampTz('checked_at', 6);
            $table->timestampTz('valid_until', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'sequence']);
            $table->unique(['tenant_id', 'id']);
        });

        $pg = $this->pg();
        $pg->grant('conditions', ['select', 'insert', 'update']);
        $pg->grantAppendOnly('pre_disbursement_checks');
        $pg->immutable('pre_disbursement_checks');
        foreach (['conditions', 'pre_disbursement_checks'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_disbursement_checks');
        Schema::dropIfExists('conditions');
    }
};
