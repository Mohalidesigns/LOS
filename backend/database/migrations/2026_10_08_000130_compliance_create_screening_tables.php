<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M18 Compliance: screening runs and alerts with four-eyes disposition
 * (FR-CUS-005, FR-CMP-011/013/014/017). A run is immutable evidence (list
 * version, provider reference); alerts carry the two-person decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('party_id');
            $table->uuid('application_id')->nullable();
            $table->string('trigger', 24);
            $table->string('subject_name', 300);
            $table->string('provider_reference', 128);
            $table->string('list_version', 64);
            $table->unsignedSmallInteger('hit_count');
            $table->string('screened_by', 64);
            $table->timestampTz('screened_at', 6);
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
            $table->index(['tenant_id', 'party_id', 'screened_at']);
        });
        DB::statement("alter table screening_runs add constraint screening_runs_trigger_chk check (trigger in ('intake', 'pre_disbursement', 'manual', 'periodic'))");

        Schema::create('screening_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('screening_run_id');
            $table->uuid('party_id');
            $table->uuid('application_id')->nullable();
            $table->uuid('org_unit_id')->nullable();
            $table->string('category', 24);
            $table->string('list_name', 200);
            $table->string('list_version', 64);
            $table->string('entry_id', 64);
            $table->string('matched_name', 300);
            $table->decimal('score', 5, 2);
            $table->string('status', 24);
            $table->string('proposed_decision', 16)->nullable();
            $table->text('proposed_reason')->nullable();
            $table->string('evidence_ref', 200)->nullable();
            $table->uuid('proposed_by')->nullable();
            $table->timestampTz('proposed_at', 6)->nullable();
            $table->uuid('confirmed_by')->nullable();
            $table->timestampTz('confirmed_at', 6)->nullable();
            $table->text('confirmation_note')->nullable();
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'screening_run_id'])->references(['tenant_id', 'id'])->on('screening_runs');
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table screening_alerts add constraint screening_alerts_status_chk check (status in ('open', 'pending_confirmation', 'cleared', 'confirmed_match'))");
        DB::statement("alter table screening_alerts add constraint screening_alerts_category_chk check (category in ('sanction', 'pep', 'adverse_media', 'watchlist'))");
        DB::statement("alter table screening_alerts add constraint screening_alerts_decision_chk check (proposed_decision is null or proposed_decision in ('clear', 'true_match'))");
        DB::statement('alter table screening_alerts add constraint screening_alerts_four_eyes_chk check (confirmed_by is null or confirmed_by <> proposed_by)');

        $pg = $this->pg();
        $pg->grantAppendOnly('screening_runs');
        $pg->tenantRls('screening_runs');
        $pg->immutable('screening_runs');
        $pg->grantCrud('screening_alerts');
        $pg->tenantRls('screening_alerts');
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_alerts');
        Schema::dropIfExists('screening_runs');
    }
};
