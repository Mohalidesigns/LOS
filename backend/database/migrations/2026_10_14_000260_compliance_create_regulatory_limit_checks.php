<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Regulatory limit checks (FR-CMP-023/003): each check is evidence, with the
 * pack rule and prudential base it used, so it can be re-evaluated later.
 * Append-only and immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_limit_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('limit_type', 32);
            $table->string('stage', 32);
            $table->string('outcome', 16);
            $table->text('detail');
            $table->jsonb('rule_ref')->nullable();
            $table->jsonb('base_ref')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('base_amount', 22, 4)->nullable();
            $table->decimal('limit_percent', 7, 4)->nullable();
            $table->decimal('limit_amount', 22, 4)->nullable();
            $table->decimal('existing_amount', 22, 4)->nullable();
            $table->decimal('proposed_amount', 22, 4)->nullable();
            $table->decimal('total_amount', 22, 4)->nullable();
            $table->jsonb('facilities');
            $table->string('checked_by', 64);
            $table->timestampTz('checked_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->index(['tenant_id', 'application_id', 'checked_at']);
        });
        DB::statement("alter table regulatory_limit_checks add constraint regulatory_limit_checks_outcome_chk check (outcome in ('pass', 'breach', 'unavailable', 'not_applicable'))");
        DB::statement("alter table regulatory_limit_checks add constraint regulatory_limit_checks_stage_chk check (stage in ('approval', 'pre_disbursement'))");

        $pg = $this->pg();
        $pg->grantAppendOnly('regulatory_limit_checks');
        $pg->immutable('regulatory_limit_checks');
        $pg->tenantRls('regulatory_limit_checks');
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_limit_checks');
    }
};
