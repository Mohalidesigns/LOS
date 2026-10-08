<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M02 Identity & Access (FR-SEC-001..008, 011, 013..015; LOS-FR-278, LOS-FR-302).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('kind', 16); // human | service
            $table->string('email', 254);
            $table->string('name', 200);
            $table->string('password')->nullable(); // Argon2id; null for service accounts
            $table->string('status', 16)->default('active'); // active | disabled
            $table->uuid('home_legal_entity_id')->nullable();
            $table->uuid('home_org_unit_id')->nullable();
            $table->text('mfa_secret')->nullable(); // envelope-encrypted with the tenant DEK
            $table->timestampTz('mfa_confirmed_at')->nullable();
            $table->bigInteger('mfa_last_used_step')->nullable(); // TOTP replay protection
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('password_changed_at')->nullable();
            // Bumped on every role/permission change: sessions holding an older
            // value are rejected (FR-SEC-015 forced re-authentication).
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'home_legal_entity_id'])->references(['tenant_id', 'id'])->on('legal_entities');
            $table->foreign(['tenant_id', 'home_org_unit_id'])->references(['tenant_id', 'id'])->on('org_units');
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement('create unique index users_tenant_email_unique on users (tenant_id, lower(email))');
        DB::statement("alter table users add constraint users_kind_chk check (kind in ('human', 'service'))");
        DB::statement("alter table users add constraint users_status_chk check (status in ('active', 'disabled'))");

        // Framework session store. Read before the tenant is known, so no RLS;
        // payload is encrypted (SESSION_ENCRYPT) and carries no business data.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Sanctum tokens for service and partner principals (FR-SEC-014).
        // Looked up before the tenant is known (only the SHA-256 hash is stored),
        // so no RLS; tenant_id is used to establish the tenant context.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });

        // Code-defined permission catalogue (TRD §8.1). Global; tenants bundle
        // permissions into roles but cannot invent them.
        Schema::create('permissions', function (Blueprint $table) {
            $table->string('code', 96)->primary();
            $table->string('resource', 64);
            $table->string('action', 64);
            $table->string('module', 32);
            $table->text('description');
            $table->boolean('is_sensitive')->default(false);
            $table->timestampTz('synced_at');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 64);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_template')->default(false);
            $table->string('template_key', 64)->nullable();
            $table->uuid('cloned_from_id')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('role_id');
            $table->string('permission_code', 96);
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['role_id', 'permission_code']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign('permission_code')->references('code')->on('permissions');
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->uuid('role_id');
            // {legal_entity_ids[], org_unit_id, product_ids[], currencies[], max_amount{amount,currency}, segments[], portfolio_tags[]}
            $table->jsonb('scope');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->uuid('granted_by');
            $table->uuid('change_request_id')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->uuid('revoke_change_request_id')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'role_id']);
        });
        DB::statement("alter table role_assignments add constraint role_assignments_scope_chk check (jsonb_typeof(scope) = 'object')");
        DB::statement('alter table role_assignments add constraint role_assignments_validity_chk check (valid_to is null or valid_to > valid_from)');

        // SoD matrix (FR-SEC-006): mutually exclusive permission pairs or role pairs.
        Schema::create('sod_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('kind', 24); // permission_pair | role_pair
            $table->string('left_ref', 96);
            $table->string('right_ref', 96);
            $table->text('description');
            $table->boolean('enabled')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });
        DB::statement("alter table sod_rules add constraint sod_rules_kind_chk check (kind in ('permission_pair', 'role_pair'))");
        DB::statement('alter table sod_rules add constraint sod_rules_distinct_chk check (left_ref <> right_ref)');
        DB::statement('create unique index sod_rules_pair_unique on sod_rules (tenant_id, kind, least(left_ref, right_ref), greatest(left_ref, right_ref))');

        // Delegation of an assignment, always with mandatory expiry (FR-SEC-008).
        Schema::create('delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('delegator_id');
            $table->uuid('delegate_id');
            $table->uuid('role_assignment_id');
            $table->text('reason');
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'delegator_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'delegate_id'])->references(['tenant_id', 'id'])->on('users');
            $table->foreign(['tenant_id', 'role_assignment_id'])->references(['tenant_id', 'id'])->on('role_assignments');
            $table->index(['tenant_id', 'delegate_id']);
        });
        DB::statement('alter table delegations add constraint delegations_validity_chk check (valid_to > valid_from)');
        DB::statement('alter table delegations add constraint delegations_self_chk check (delegator_id <> delegate_id)');

        $pg = $this->pg();
        foreach (['users', 'roles', 'role_permissions', 'role_assignments', 'sod_rules', 'delegations'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
        $pg->grantCrud('sessions');
        $pg->grantCrud('personal_access_tokens');
        $pg->grantSequence('personal_access_tokens_id_seq');
        $pg->grant('permissions', ['select']);
    }

    public function down(): void
    {
        foreach (['delegations', 'sod_rules', 'role_assignments', 'role_permissions', 'roles', 'permissions', 'personal_access_tokens', 'sessions', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
