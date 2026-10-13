<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M11 Approvals: approval requests (one per routing of a recommended
 * application), votes and the conditions they attach (FR-APV-001..012).
 * Votes are evidence and append-only; a request and its conditions change
 * status but are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 16);
            $table->string('matrix_key', 96);
            $table->uuid('matrix_version_id');
            $table->unsignedInteger('matrix_version_no');
            $table->integer('matrix_row')->nullable();
            $table->jsonb('facts');
            $table->jsonb('routing_trace');
            $table->jsonb('levels');
            $table->unsignedSmallInteger('current_level');
            $table->uuid('decision_id')->nullable();
            $table->decimal('exposure_amount', 20, 4);
            $table->char('exposure_currency', 3);
            $table->string('recommended_by', 64);
            $table->timestampTz('opened_at', 6);
            $table->timestampTz('decided_at', 6)->nullable();
            $table->unsignedSmallInteger('validity_days');
            $table->timestampTz('valid_until', 6)->nullable();
            $table->jsonb('counter_terms')->nullable();
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'matrix_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
            $table->unique(['application_id', 'sequence']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("alter table approval_requests add constraint approval_requests_status_chk check (status in ('pending', 'approved', 'declined', 'counter_offered', 'expired', 'superseded'))");
        // at most one live request per application
        DB::statement("create unique index approval_requests_one_pending on approval_requests (application_id) where status = 'pending'");

        Schema::create('approval_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('request_id');
            $table->uuid('application_id');
            $table->unsignedSmallInteger('level_index');
            $table->string('level_code', 16);
            $table->string('voter_id', 64);
            $table->string('decision', 32);
            $table->text('rationale');
            $table->jsonb('reason_codes');
            $table->jsonb('conditions');
            $table->jsonb('counter_terms')->nullable();
            $table->jsonb('authority_basis');
            $table->string('step_up_ref', 128)->nullable();
            $table->timestampTz('voted_at', 6);
            $table->foreign(['tenant_id', 'request_id'])->references(['tenant_id', 'id'])->on('approval_requests');
            $table->unique(['request_id', 'level_index']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table approval_votes add constraint approval_votes_decision_chk check (decision in ('approve', 'approve_with_conditions', 'decline', 'counter_offer'))");

        Schema::create('approval_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('request_id');
            $table->uuid('vote_id');
            $table->string('kind', 16);
            $table->text('description');
            $table->string('status', 16);
            $table->timestampTz('created_at', 6);
            $table->foreign(['tenant_id', 'request_id'])->references(['tenant_id', 'id'])->on('approval_requests');
            $table->foreign(['tenant_id', 'vote_id'])->references(['tenant_id', 'id'])->on('approval_votes');
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table approval_conditions add constraint approval_conditions_kind_chk check (kind in ('precedent', 'subsequent'))");
        DB::statement("alter table approval_conditions add constraint approval_conditions_status_chk check (status in ('open', 'satisfied', 'waived'))");

        $pg = $this->pg();
        $pg->grant('approval_requests', ['select', 'insert', 'update']);
        $pg->grant('approval_conditions', ['select', 'insert', 'update']);
        $pg->grantAppendOnly('approval_votes');
        $pg->immutable('approval_votes');
        foreach (['approval_requests', 'approval_votes', 'approval_conditions'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_conditions');
        Schema::dropIfExists('approval_votes');
        Schema::dropIfExists('approval_requests');
    }
};
