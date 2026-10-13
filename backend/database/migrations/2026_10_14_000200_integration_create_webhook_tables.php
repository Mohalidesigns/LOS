<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Outbound webhooks (TRD §10.1, FR-NTF-001, FR-HND-001): subscriber endpoints
 * with an encrypted signing secret, the events published, and a delivery log.
 * Events are evidence and append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 120);
            $table->string('url', 500);
            $table->text('secret_enc');
            $table->jsonb('events');
            $table->string('status', 16);
            $table->string('created_by', 64);
            $table->timestampsTz(6);
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("alter table webhook_endpoints add constraint webhook_endpoints_status_chk check (status in ('active', 'disabled'))");

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('type', 96);
            $table->string('subject_type', 64);
            $table->string('subject_id', 64);
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', 6);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('event_id');
            $table->uuid('endpoint_id');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestampTz('last_attempt_at', 6)->nullable();
            $table->timestampTz('delivered_at', 6)->nullable();
            $table->timestampTz('created_at', 6);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('webhook_events');
            $table->foreign(['tenant_id', 'endpoint_id'])->references(['tenant_id', 'id'])->on('webhook_endpoints');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'event_id']);
        });
        DB::statement("alter table webhook_deliveries add constraint webhook_deliveries_status_chk check (status in ('pending', 'delivered', 'failing', 'skipped'))");

        $pg = $this->pg();
        $pg->grant('webhook_endpoints', ['select', 'insert', 'update']);
        $pg->grant('webhook_deliveries', ['select', 'insert', 'update']);
        $pg->grantAppendOnly('webhook_events');
        $pg->immutable('webhook_events');
        foreach (['webhook_endpoints', 'webhook_events', 'webhook_deliveries'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
    }
};
