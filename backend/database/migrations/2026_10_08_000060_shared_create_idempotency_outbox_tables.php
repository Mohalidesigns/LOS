<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Idempotency (PR-05, TRD §10.1, FR-CBA-007) and transactional outbox (FR-CBA-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('scope', 32); // http | integration
            $table->string('principal_id', 128);
            $table->string('key', 255);
            $table->char('request_hash', 64);
            $table->string('status', 16); // in_progress | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'scope', 'principal_id', 'key']);
        });

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('topic', 128);
            $table->string('aggregate_type', 64)->nullable();
            $table->string('aggregate_id', 128)->nullable();
            $table->jsonb('payload');
            $table->string('idempotency_key', 255)->nullable();
            $table->string('status', 24); // pending | dispatched | parked | failed | rejected
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts');
            $table->timestampTz('available_at');
            $table->string('last_error_class', 32)->nullable();
            $table->string('last_error_code', 96)->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('created_by', 128);
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['status', 'available_at']);
            $table->index(['tenant_id', 'aggregate_type', 'aggregate_id']);
        });
        DB::statement("alter table outbox_messages add constraint outbox_messages_status_chk check (status in ('pending', 'dispatched', 'parked', 'failed', 'rejected'))");
        DB::statement('create unique index outbox_messages_idem_unique on outbox_messages (tenant_id, idempotency_key) where idempotency_key is not null');

        $pg = $this->pg();
        foreach (['idempotency_keys', 'outbox_messages'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('idempotency_keys');
    }
};
