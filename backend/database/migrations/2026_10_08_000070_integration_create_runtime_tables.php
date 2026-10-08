<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M17 Integration Runtime (FR-CBA-001, 007, 008, 010, 011, 016, 020; TRD §11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adapter_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('legal_entity_id')->nullable();
            $table->string('port', 64);
            $table->string('adapter_key', 64);
            $table->string('adapter_version', 32);
            $table->jsonb('config'); // endpoints, mappings, credentials *reference* (never the secret)
            $table->string('processing_location', 64);
            $table->jsonb('fault_script')->nullable(); // simulators, non-production only
            $table->string('status', 16)->default('active');
            $table->boolean('allow_in_production')->default(false);
            $table->uuid('change_request_id')->nullable();
            $table->timestampsTz(6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign(['tenant_id', 'legal_entity_id'])->references(['tenant_id', 'id'])->on('legal_entities');
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("create unique index adapter_bindings_active_unique on adapter_bindings (tenant_id, port, coalesce(legal_entity_id, '00000000-0000-0000-0000-000000000000'::uuid)) where status = 'active'");

        Schema::create('integration_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('binding_id')->nullable();
            $table->string('port', 64);
            $table->string('operation', 96);
            $table->string('adapter_key', 64);
            $table->string('adapter_version', 32);
            $table->string('contract_version', 16)->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->jsonb('request')->nullable();  // PII-masked
            $table->jsonb('response')->nullable(); // PII-masked
            $table->string('outcome', 32); // success | retryable | non_retryable | requires_intervention | business_rejection | short_circuited
            $table->string('error_code', 96)->nullable();
            $table->unsignedInteger('latency_ms');
            $table->timestampTz('started_at', 6);
            $table->timestampTz('finished_at', 6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'started_at']);
            $table->index(['tenant_id', 'correlation_id']);
            $table->index(['tenant_id', 'idempotency_key']);
        });

        Schema::create('circuit_breakers', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->string('breaker_key', 160);
            $table->string('state', 16); // closed | open | half_open
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('opened_at', 6)->nullable();
            $table->timestampTz('updated_at', 6);
            $table->primary(['tenant_id', 'breaker_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants');
        });

        Schema::create('circuit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('breaker_key', 160);
            $table->string('from_state', 16);
            $table->string('to_state', 16);
            $table->text('reason');
            $table->timestampTz('occurred_at', 6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'breaker_key']);
        });

        // Persistent state of the CBA simulator, so effects survive across
        // workers (needed for timeout_then_success + lookup-before-retry).
        Schema::create('cba_simulator_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('kind', 32);
            $table->string('record_key', 255);
            $table->jsonb('data');
            $table->timestampTz('created_at', 6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'kind', 'record_key']);
        });

        $pg = $this->pg();
        foreach (['adapter_bindings', 'circuit_breakers', 'cba_simulator_records'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
        foreach (['integration_calls', 'circuit_events'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        foreach (['cba_simulator_records', 'circuit_events', 'circuit_breakers', 'integration_calls', 'adapter_bindings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
