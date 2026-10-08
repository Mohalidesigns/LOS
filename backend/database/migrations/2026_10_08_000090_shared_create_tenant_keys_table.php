<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-tenant data encryption keys, stored only in wrapped form (KEK held by
 * the KeyManagementPort) (FR-SEC-016, FR-SEC-017, TRD §3 PII crypto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('purpose', 32); // pii_dek | blind_index
            $table->unsignedInteger('version');
            $table->text('wrapped_key');
            $table->string('kek_id', 64);
            $table->string('status', 16); // active | retired
            $table->timestampTz('created_at', 6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'purpose', 'version']);
        });

        $pg = $this->pg();
        $pg->grant('tenant_keys', ['select', 'insert', 'update']);
        $pg->tenantRls('tenant_keys');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_keys');
    }
};
