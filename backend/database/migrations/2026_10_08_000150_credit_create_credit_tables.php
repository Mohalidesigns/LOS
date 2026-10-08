<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M08 Credit: bureau reports, decision snapshots, policy exceptions and
 * credit memos. All four are evidence and therefore append-only: a new
 * decision or memo is a new row, never an update (FR-CRD-014, LOS-CON-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('party_id');
            $table->string('bureau', 64);
            $table->string('report_reference', 128);
            $table->string('identifier_type', 16);
            $table->boolean('hit');
            $table->jsonb('profile');
            $table->string('consent_reference', 128);
            $table->string('pulled_by', 64);
            $table->timestampTz('pulled_at', 6);
            $table->timestampTz('valid_until', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id', 'pulled_at']);
        });

        Schema::create('decision_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('sequence');
            $table->string('outcome', 16);
            $table->string('risk_grade', 16);
            $table->uuid('rule_set_version_id');
            $table->string('rule_set_key', 96);
            $table->unsignedInteger('rule_set_version_no');
            $table->string('evaluator_version', 16);
            $table->uuid('bureau_report_id')->nullable();
            $table->jsonb('facts');
            $table->jsonb('outputs');
            $table->jsonb('trace');
            $table->string('decided_by', 64);
            $table->timestampTz('decided_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'rule_set_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
            $table->foreign(['tenant_id', 'bureau_report_id'])->references(['tenant_id', 'id'])->on('bureau_reports');
            $table->unique(['application_id', 'sequence']);
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("alter table decision_snapshots add constraint decision_snapshots_outcome_chk check (outcome in ('approve', 'refer', 'decline', 'counter_offer'))");

        Schema::create('policy_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('decision_id');
            $table->string('reason_code', 48);
            $table->text('justification');
            $table->string('evidence_ref', 200)->nullable();
            $table->string('severity', 8);
            $table->string('raised_by', 64);
            $table->timestampTz('raised_at', 6);
            $table->foreign(['tenant_id', 'decision_id'])->references(['tenant_id', 'id'])->on('decision_snapshots');
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table policy_exceptions add constraint policy_exceptions_severity_chk check (severity in ('low', 'medium', 'high'))");

        Schema::create('credit_memos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('version_no');
            $table->uuid('decision_id');
            $table->jsonb('sections');
            $table->text('narrative');
            $table->string('recommendation', 16);
            $table->decimal('recommended_amount', 20, 4)->nullable();
            $table->unsignedSmallInteger('recommended_tenor_months')->nullable();
            $table->jsonb('conditions');
            $table->string('authored_by', 64);
            $table->timestampTz('authored_at', 6);
            $table->foreign(['tenant_id', 'decision_id'])->references(['tenant_id', 'id'])->on('decision_snapshots');
            $table->unique(['application_id', 'version_no']);
        });
        DB::statement("alter table credit_memos add constraint credit_memos_recommendation_chk check (recommendation in ('approve', 'decline', 'counter_offer'))");

        $pg = $this->pg();
        foreach (['bureau_reports', 'decision_snapshots', 'policy_exceptions', 'credit_memos'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->tenantRls($t);
            $pg->immutable($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_memos');
        Schema::dropIfExists('policy_exceptions');
        Schema::dropIfExists('decision_snapshots');
        Schema::dropIfExists('bureau_reports');
    }
};
