<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M01 Platform: tenancy (FR-TEN-001, FR-TEN-003, D-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Installation identity (one row). Not tenant-owned.
        Schema::create('installation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('installation_uuid')->unique();
            $table->string('environment', 32);
            $table->timestampTz('created_at')->useCurrent();
        });

        // Root of tenancy. Not itself tenant-scoped (it must be readable to
        // resolve the tenant before authentication); holds no business data.
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 200);
            $table->string('status', 16)->default('active');
            $table->string('hostname', 255)->nullable()->unique();
            $table->timestampsTz();
        });

        Schema::create('legal_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 32);
            $table->string('name', 200);
            $table->char('jurisdiction', 2);
            $table->string('licence_category', 64);
            $table->char('base_currency', 3);
            $table->string('timezone', 64);
            $table->jsonb('org_level_labels');
            $table->string('status', 16)->default('active');
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('org_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('legal_entity_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 32);
            $table->string('name', 200);
            $table->unsignedSmallInteger('depth');
            $table->string('status', 16)->default('active');
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'legal_entity_id'])->references(['tenant_id', 'id'])->on('legal_entities');
            $table->unique(['tenant_id', 'legal_entity_id', 'code']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'parent_id']);
        });
        Schema::table('org_units', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'parent_id'])->references(['tenant_id', 'id'])->on('org_units');
        });

        // Closure table: one row per (ancestor, descendant) incl. self at depth 0.
        // Subtree scope checks become a single indexed join (TRD §2.3).
        Schema::create('org_unit_closure', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('ancestor_id');
            $table->uuid('descendant_id');
            $table->unsignedSmallInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['tenant_id', 'descendant_id']);
            $table->foreign(['tenant_id', 'ancestor_id'])->references(['tenant_id', 'id'])->on('org_units')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'descendant_id'])->references(['tenant_id', 'id'])->on('org_units')->cascadeOnDelete();
        });

        $pg = $this->pg();
        $pg->grant('installation', ['select', 'insert']);
        $pg->grant('tenants', ['select', 'insert', 'update']);
        foreach (['legal_entities', 'org_units', 'org_unit_closure'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
        DB::statement("alter table legal_entities add constraint legal_entities_currency_chk check (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("alter table legal_entities add constraint legal_entities_labels_chk check (jsonb_typeof(org_level_labels) = 'array')");
    }

    public function down(): void
    {
        foreach (['org_unit_closure', 'org_units', 'legal_entities', 'tenants', 'installation'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
