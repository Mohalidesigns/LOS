<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * M19 Audit, P1-AUD-01 (FR-AUD-006; TRD §5.4): who read PII or application
 * data, when, from where and for what purpose. Append-only for every role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pii_access_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->timestampTz('occurred_at', 6);
            $table->string('actor_type', 24);
            $table->string('actor_id', 128);
            $table->string('on_behalf_of', 128)->nullable();
            $table->string('resource_type', 64);
            $table->string('resource_id', 128)->nullable();
            $table->string('route', 128);
            $table->boolean('contains_pii')->default(false);
            $table->string('purpose', 64)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'resource_type', 'resource_id']);
            $table->index(['tenant_id', 'actor_id']);
        });

        $pg = $this->pg();
        $pg->grantAppendOnly('pii_access_log');
        $pg->tenantRls('pii_access_log');
        $pg->immutable('pii_access_log');
    }

    public function down(): void
    {
        Schema::dropIfExists('pii_access_log');
    }
};
