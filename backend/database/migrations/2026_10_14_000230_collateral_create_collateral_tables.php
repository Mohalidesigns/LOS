<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M09 Collateral (FR-COL-001..006, 008): items of configurable types with
 * haircut and forced-sale value, valuations and insurance policies (both
 * append-only evidence), perfection steps, and allocations of lending value
 * across facilities. Over-allocation is refused by the handler under a row
 * lock on the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaterals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('origin_application_id');
            $table->string('type_code', 48);
            $table->string('type_source', 8);
            $table->uuid('type_version_id')->nullable();
            $table->string('category', 32);
            $table->text('description');
            $table->uuid('owner_party_id')->nullable();
            $table->string('owner_name', 200);
            $table->char('state_code', 2)->nullable();
            $table->char('currency', 3);
            $table->decimal('market_value', 20, 4);
            $table->decimal('haircut_percent', 7, 4);
            $table->decimal('forced_sale_value', 20, 4);
            $table->string('value_basis', 16);
            $table->boolean('requires_valuation');
            $table->date('valuation_valid_until')->nullable();
            $table->string('insurance_requirement', 16);
            $table->text('insurance_waiver_reason')->nullable();
            $table->uuid('insurance_waiver_change_request_id')->nullable();
            $table->string('status', 16);
            $table->string('created_by', 64);
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'origin_application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'owner_party_id']);
        });
        DB::statement("alter table collaterals add constraint collaterals_basis_chk check (value_basis in ('declared', 'valuation'))");
        DB::statement("alter table collaterals add constraint collaterals_insurance_chk check (insurance_requirement in ('not_required', 'required', 'waiver_pending', 'waived'))");
        DB::statement("alter table collaterals add constraint collaterals_status_chk check (status in ('active', 'released'))");
        DB::statement('alter table collaterals add constraint collaterals_values_chk check (market_value >= 0 and forced_sale_value >= 0 and forced_sale_value <= market_value and haircut_percent between 0 and 100)');

        Schema::create('collateral_valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('collateral_id');
            $table->unsignedInteger('sequence');
            $table->string('valuer', 200);
            $table->date('valuation_date');
            $table->string('method', 32);
            $table->decimal('market_value', 20, 4);
            $table->decimal('forced_sale_value', 20, 4);
            $table->char('currency', 3);
            $table->date('valid_until')->nullable();
            $table->uuid('report_document_id')->nullable();
            $table->uuid('report_version_id')->nullable();
            $table->text('note')->nullable();
            $table->string('recorded_by', 64);
            $table->timestampTz('recorded_at', 6);
            $table->foreign(['tenant_id', 'collateral_id'])->references(['tenant_id', 'id'])->on('collaterals');
            $table->unique(['collateral_id', 'sequence']);
        });

        Schema::create('collateral_perfection_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('collateral_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('code', 48);
            $table->string('name', 200);
            $table->string('timing', 16);
            $table->uuid('responsible_user_id')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 16);
            $table->uuid('evidence_document_id')->nullable();
            $table->uuid('evidence_version_id')->nullable();
            $table->string('evidence_reference', 200)->nullable();
            $table->text('note')->nullable();
            $table->string('completed_by', 64)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->text('waiver_reason')->nullable();
            $table->uuid('waiver_change_request_id')->nullable();
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'collateral_id'])->references(['tenant_id', 'id'])->on('collaterals');
            $table->unique(['collateral_id', 'code']);
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("alter table collateral_perfection_steps add constraint perfection_timing_chk check (timing in ('precedent', 'subsequent'))");
        DB::statement("alter table collateral_perfection_steps add constraint perfection_status_chk check (status in ('pending', 'in_progress', 'completed', 'waiver_pending', 'waived'))");
        DB::statement("alter table collateral_perfection_steps add constraint perfection_evidence_chk check (status <> 'completed' or evidence_version_id is not null or evidence_reference is not null)");

        Schema::create('collateral_insurance_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('collateral_id');
            $table->string('policy_no', 64);
            $table->string('insurer', 200);
            $table->decimal('sum_insured', 20, 4);
            $table->char('currency', 3);
            $table->date('start_date');
            $table->date('expiry_date');
            $table->boolean('bank_interest_noted');
            $table->uuid('document_id')->nullable();
            $table->uuid('document_version_id')->nullable();
            $table->string('recorded_by', 64);
            $table->timestampTz('recorded_at', 6);
            $table->foreign(['tenant_id', 'collateral_id'])->references(['tenant_id', 'id'])->on('collaterals');
            $table->index(['tenant_id', 'collateral_id']);
        });
        DB::statement('alter table collateral_insurance_policies add constraint insurance_dates_chk check (expiry_date > start_date)');

        Schema::create('collateral_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('collateral_id');
            $table->uuid('application_id');
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->string('created_by', 64);
            $table->string('updated_by', 64);
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'collateral_id'])->references(['tenant_id', 'id'])->on('collaterals');
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['collateral_id', 'application_id']);
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table collateral_allocations add constraint allocation_status_chk check (status in ('active', 'released'))");
        DB::statement('alter table collateral_allocations add constraint allocation_amount_chk check (amount >= 0)');

        $pg = $this->pg();
        foreach (['collaterals', 'collateral_perfection_steps', 'collateral_allocations'] as $t) {
            $pg->grant($t, ['select', 'insert', 'update']);
        }
        foreach (['collateral_valuations', 'collateral_insurance_policies'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->immutable($t);
        }
        foreach (['collaterals', 'collateral_valuations', 'collateral_perfection_steps', 'collateral_insurance_policies', 'collateral_allocations'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        foreach (['collateral_allocations', 'collateral_insurance_policies', 'collateral_perfection_steps', 'collateral_valuations', 'collaterals'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
