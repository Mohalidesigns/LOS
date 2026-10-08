<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Granular consent with withdrawal and full history (FR-CUS-008, FR-CMP-010,
 * FR-CMP-021, FR-CMP-032). Append-only: the current state of a purpose is its
 * latest row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('party_id');
            $table->string('purpose', 32);
            $table->string('action', 16);
            $table->string('channel', 16);
            $table->string('terms_version', 32);
            $table->string('evidence_ref', 200)->nullable();
            $table->uuid('application_id')->nullable();
            $table->string('recorded_by', 64);
            $table->timestampTz('recorded_at', 6);
            $table->foreign(['tenant_id', 'party_id'])->references(['tenant_id', 'id'])->on('parties');
            $table->index(['tenant_id', 'party_id', 'purpose', 'recorded_at']);
        });
        DB::statement("alter table party_consents add constraint party_consents_purpose_chk check (purpose in ('data_processing', 'credit_bureau', 'credit_reporting', 'marketing', 'third_party_sharing'))");
        DB::statement("alter table party_consents add constraint party_consents_action_chk check (action in ('grant', 'withdraw'))");
        DB::statement("alter table party_consents add constraint party_consents_channel_chk check (channel in ('branch', 'digital', 'call_centre', 'agent', 'api'))");

        $pg = $this->pg();
        $pg->grantAppendOnly('party_consents');
        $pg->tenantRls('party_consents');
        $pg->immutable('party_consents');
    }

    public function down(): void
    {
        Schema::dropIfExists('party_consents');
    }
};
