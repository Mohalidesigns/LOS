<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M14 Disbursement: instructions (maker-checker release, booking saga state),
 * the saga step log, and daily reconciliation runs and breaks
 * (FR-DSB-001..011, FR-CBA-009/017, LOS-FR-313). Step logs and runs are
 * evidence and append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 24);
            $table->uuid('offer_id');
            $table->uuid('agreement_id');
            $table->decimal('principal_amount', 20, 4);
            $table->decimal('net_amount', 20, 4);
            $table->char('currency', 3);
            $table->jsonb('fees');
            $table->date('value_date');
            $table->jsonb('destination');
            $table->text('account_no_enc');
            $table->uuid('change_request_id')->nullable();
            $table->string('made_by', 64);
            $table->timestampTz('made_at', 6);
            $table->string('released_by', 64)->nullable();
            $table->timestampTz('released_at', 6)->nullable();
            $table->string('cba_customer_id', 64)->nullable();
            $table->string('loan_account_no', 64)->nullable();
            $table->string('posting_ref', 64)->nullable();
            $table->jsonb('fee_posting_refs')->nullable();
            $table->jsonb('cba_schedule')->nullable();
            $table->jsonb('variance')->nullable();
            $table->string('last_error_code', 96)->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedSmallInteger('saga_runs')->default(0);
            $table->timestampTz('booked_at', 6)->nullable();
            $table->uuid('advice_document_id')->nullable();
            $table->uuid('advice_version_id')->nullable();
            $table->string('cancelled_by', 64)->nullable();
            $table->timestampTz('cancelled_at', 6)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'sequence']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("alter table disbursements add constraint disbursements_status_chk check (status in ('pending_release', 'pending_cba', 'failed_retryable', 'failed_intervention', 'compensated', 'booked', 'cancelled'))");
        // one live instruction per application (FR-DSB-005: never a second disbursement by accident)
        DB::statement("create unique index disbursements_one_live on disbursements (application_id) where status not in ('compensated', 'cancelled')");

        Schema::create('disbursement_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('disbursement_id');
            $table->unsignedSmallInteger('run');
            $table->string('step', 32);
            $table->string('outcome', 16);
            $table->jsonb('detail');
            $table->string('error_code', 96)->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->foreign(['tenant_id', 'disbursement_id'])->references(['tenant_id', 'id'])->on('disbursements');
            $table->index(['tenant_id', 'disbursement_id']);
        });
        DB::statement("alter table disbursement_steps add constraint disbursement_steps_outcome_chk check (outcome in ('succeeded', 'skipped', 'failed', 'compensated'))");

        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedInteger('los_postings');
            $table->unsignedInteger('cba_postings');
            $table->unsignedInteger('matched');
            $table->unsignedInteger('breaks');
            $table->string('ran_by', 64);
            $table->timestampTz('ran_at', 6);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'ran_at']);
        });

        Schema::create('reconciliation_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('run_id');
            $table->string('kind', 24);
            $table->string('reference', 200);
            $table->string('posting_ref', 64)->nullable();
            $table->string('account_no', 64)->nullable();
            $table->decimal('los_amount', 20, 4)->nullable();
            $table->decimal('cba_amount', 20, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->uuid('disbursement_id')->nullable();
            $table->string('status', 16);
            $table->string('resolved_by', 64)->nullable();
            $table->timestampTz('resolved_at', 6)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreign(['tenant_id', 'run_id'])->references(['tenant_id', 'id'])->on('reconciliation_runs');
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("alter table reconciliation_breaks add constraint reconciliation_breaks_kind_chk check (kind in ('missing_in_cba', 'missing_in_los', 'amount_mismatch'))");
        DB::statement("alter table reconciliation_breaks add constraint reconciliation_breaks_status_chk check (status in ('open', 'resolved'))");

        $pg = $this->pg();
        $pg->grant('disbursements', ['select', 'insert', 'update']);
        $pg->grant('reconciliation_breaks', ['select', 'insert', 'update']);
        foreach (['disbursement_steps', 'reconciliation_runs'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->immutable($t);
        }
        foreach (['disbursements', 'disbursement_steps', 'reconciliation_runs', 'reconciliation_breaks'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_breaks');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('disbursement_steps');
        Schema::dropIfExists('disbursements');
    }
};
