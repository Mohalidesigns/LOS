<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Generic maker-checker engine (FR-SEC-007, TRD §5.2 / §6.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('action_type', 96);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->jsonb('payload');
            $table->char('payload_hash', 64);
            // Fingerprint of the state the maker saw; execution fails stale if it changed.
            $table->char('state_fingerprint', 64);
            $table->string('required_checker_permission', 96);
            $table->string('status', 24); // pending | executed | rejected | cancelled | failed_stale | failed
            $table->uuid('maker_id');
            $table->text('maker_reason')->nullable();
            $table->uuid('checker_id')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('step_up_ref', 64)->nullable();
            $table->jsonb('execution_result')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'maker_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'checker_id'])->references(['tenant_id', 'id'])->on('users');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });
        DB::statement("alter table change_requests add constraint change_requests_status_chk check (status in ('pending', 'executed', 'rejected', 'cancelled', 'failed_stale', 'failed'))");
        // Checker must differ from maker, enforced by the database as well as the engine.
        DB::statement('alter table change_requests add constraint change_requests_four_eyes_chk check (checker_id is null or checker_id <> maker_id)');

        $pg = $this->pg();
        $pg->grantCrud('change_requests');
        $pg->tenantRls('change_requests');
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
