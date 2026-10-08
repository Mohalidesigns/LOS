<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M06 Application (TRD §5.2). `application_events` is the system of record:
 * append-only (no UPDATE/DELETE grant + trigger), unique (application_id,
 * version) for optimistic concurrency. `applications` and
 * `application_applicants` are projections rebuildable from the events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_sequences', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->string('scope_key', 64);
            $table->unsignedBigInteger('last_value');
            $table->primary(['tenant_id', 'scope_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('reference', 40);
            $table->uuid('legal_entity_id');
            $table->uuid('org_unit_id');
            $table->uuid('product_id');
            $table->string('product_key', 96);
            $table->uuid('product_version_id');
            $table->string('product_name', 200);
            $table->string('segment', 16);
            $table->string('channel', 16);
            $table->uuid('originator_id');
            $table->uuid('primary_party_id');
            $table->string('primary_applicant_name', 300);
            $table->decimal('requested_amount', 20, 4)->nullable();
            $table->char('currency', 3);
            $table->unsignedSmallInteger('tenor_months')->nullable();
            $table->string('purpose', 500)->nullable();
            $table->string('repayment_frequency', 16)->nullable();
            $table->jsonb('data');
            $table->string('canonical_status', 32);
            $table->string('resume_to', 32)->nullable();
            $table->string('return_to', 32)->nullable();
            $table->unsignedInteger('version');
            $table->timestampTz('submitted_at', 6)->nullable();
            $table->timestampTz('status_changed_at', 6);
            $table->timestampTz('closed_at', 6)->nullable();
            $table->string('close_reason_code', 64)->nullable();
            $table->timestampTz('expires_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'legal_entity_id'])->references(['tenant_id', 'id'])->on('legal_entities');
            $table->foreign(['tenant_id', 'org_unit_id'])->references(['tenant_id', 'id'])->on('org_units');
            $table->foreign(['tenant_id', 'product_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
            $table->foreign(['tenant_id', 'primary_party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'canonical_status']);
            $table->index(['tenant_id', 'originator_id']);
            $table->index(['tenant_id', 'org_unit_id']);
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('version');
            $table->string('type', 64);
            $table->jsonb('payload');
            $table->string('actor_type', 16);
            $table->string('actor_id', 64);
            $table->string('correlation_id', 64)->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'version']);
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('application_applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('party_id');
            $table->string('role', 16);
            $table->string('party_type', 24);
            $table->string('display_name', 300);
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->unique(['application_id', 'party_id']);
            $table->index(['tenant_id', 'party_id']);
        });
        DB::statement("alter table application_applicants add constraint application_applicants_role_chk check (role in ('primary', 'joint', 'guarantor', 'co_signer'))");

        Schema::create('field_provenance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('field_path', 128);
            $table->unsignedInteger('version');
            $table->string('source', 16);
            $table->string('source_ref', 128)->nullable();
            $table->string('recorded_by', 64);
            $table->timestampTz('recorded_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'field_path', 'version']);
        });
        DB::statement("alter table field_provenance add constraint field_provenance_source_chk check (source in ('cba', 'extraction', 'self_service', 'staff', 'partner'))");

        $pg = $this->pg();
        foreach (['application_sequences', 'applications', 'application_applicants'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
        foreach (['application_events', 'field_provenance'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->tenantRls($t);
            $pg->immutable($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_provenance');
        Schema::dropIfExists('application_applicants');
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('application_sequences');
    }
};
