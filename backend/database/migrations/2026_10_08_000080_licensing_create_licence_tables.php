<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M21 Licensing (LOS-FR-316, D-034, TRD §2.6). A licence binds to the
 * *installation*, not to a tenant, so these tables are installation-level
 * (no tenant_id / RLS). Every licence event is also written to the importing
 * tenant's hash-chained audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('licence_id', 128);
            $table->text('document'); // exact signed JSON bytes
            $table->string('signature', 128); // base64 Ed25519
            $table->string('status', 16); // active | superseded
            $table->uuid('imported_by_tenant_id')->nullable();
            $table->uuid('imported_by')->nullable();
            $table->uuid('change_request_id')->nullable();
            $table->timestampTz('imported_at');
        });
        DB::statement("create unique index licences_one_active on licences ((status)) where status = 'active'");

        Schema::create('licence_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('licence_id', 128)->nullable();
            $table->string('event', 64);
            $table->jsonb('details');
            $table->timestampTz('occurred_at', 6);
        });

        $pg = $this->pg();
        $pg->grant('licences', ['select', 'insert', 'update']);
        $pg->grantAppendOnly('licence_events');
        $pg->immutable('licence_events');
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_events');
        Schema::dropIfExists('licences');
    }
};
