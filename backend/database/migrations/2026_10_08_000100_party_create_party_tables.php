<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M05 Party (FR-CUS-001, FR-CUS-006, FR-CHN-007). Sensitive identifiers are
 * field-encrypted with a keyed blind index for exact-match dedupe (TRD §5.3);
 * names stay clear for trigram fuzzy matching and are protected by RLS,
 * scope and masking.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');

        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('type', 24);
            $table->string('display_name', 300);
            $table->string('name_normalised', 300);
            $table->uuid('org_unit_id')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('cba_customer_id', 64)->nullable();
            // individual
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 16)->nullable();
            $table->text('date_of_birth_enc')->nullable();
            $table->char('nationality', 2)->nullable();
            // limited company
            $table->string('registration_number', 32)->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('sector', 64)->nullable();
            // contact and tax (encrypted + blind index)
            $table->text('phone_enc')->nullable();
            $table->char('phone_bidx', 64)->nullable();
            $table->text('email_enc')->nullable();
            $table->char('email_bidx', 64)->nullable();
            $table->text('address_enc')->nullable();
            $table->text('tin_enc')->nullable();
            $table->char('tin_bidx', 64)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz(6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'org_unit_id'])->references(['tenant_id', 'id'])->on('org_units');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'phone_bidx']);
            $table->index(['tenant_id', 'email_bidx']);
            $table->index(['tenant_id', 'tin_bidx']);
            $table->index(['tenant_id', 'registration_number']);
        });
        DB::statement("alter table parties add constraint parties_type_chk check (type in ('individual', 'limited_company'))");
        DB::statement('create index parties_name_trgm on parties using gin (name_normalised gin_trgm_ops)');

        Schema::create('party_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('party_id');
            $table->string('type', 24);
            $table->text('value_enc');
            $table->char('value_bidx', 64);
            $table->string('value_masked', 64);
            $table->string('verification_status', 16)->default('unverified');
            $table->timestampTz('verified_at', 6)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->jsonb('verification_details')->nullable();
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->unique(['party_id', 'type']);
            $table->index(['tenant_id', 'type', 'value_bidx']);
        });
        DB::statement("alter table party_identities add constraint party_identities_type_chk check (type in ('bvn', 'nin', 'passport', 'drivers_licence', 'voters_card'))");
        DB::statement("alter table party_identities add constraint party_identities_status_chk check (verification_status in ('unverified', 'verified', 'failed'))");

        Schema::create('party_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('party_id');
            $table->uuid('related_party_id');
            $table->string('role', 32);
            $table->decimal('ownership_percent', 7, 4)->nullable();
            $table->string('notes', 500)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->foreign(['tenant_id', 'related_party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->unique(['party_id', 'related_party_id', 'role']);
            $table->index(['tenant_id', 'related_party_id']);
        });
        DB::statement("alter table party_relationships add constraint party_relationships_role_chk check (role in ('director', 'shareholder', 'beneficial_owner', 'signatory', 'company_secretary'))");
        DB::statement('alter table party_relationships add constraint party_relationships_self_chk check (party_id <> related_party_id)');
        DB::statement('alter table party_relationships add constraint party_relationships_pct_chk check (ownership_percent is null or (ownership_percent > 0 and ownership_percent <= 100))');

        $pg = $this->pg();
        foreach (['parties', 'party_identities', 'party_relationships'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('party_relationships');
        Schema::dropIfExists('party_identities');
        Schema::dropIfExists('parties');
    }
};
