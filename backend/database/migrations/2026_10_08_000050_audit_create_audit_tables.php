<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * M19 Audit (FR-AUD-001..005, 007, 012; TRD §5.4).
 * Hash-chained per tenant, append-only for every role (trigger), and the
 * runtime role holds INSERT + SELECT only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('seq');
            $table->timestampTz('occurred_at', 6);
            $table->string('actor_type', 24); // user | system | service_account | partner | anonymous
            $table->string('actor_id', 128);
            $table->jsonb('actor_roles');
            $table->char('effective_permissions_hash', 64)->nullable();
            $table->string('on_behalf_of', 128)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id', 128)->nullable();
            $table->string('action', 128);
            $table->string('permission', 96)->nullable();
            $table->string('outcome', 16); // success | denied | failure
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 128)->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_text')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('step_up_ref', 64)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'seq']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'actor_id']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'correlation_id']);
            $table->index(['tenant_id', 'action']);
        });

        Schema::create('audit_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('seq');
            $table->char('hash', 64);
            $table->string('sink', 32);
            $table->timestampTz('created_at', 6);
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'seq']);
        });

        $pg = $this->pg();
        foreach (['audit_events', 'audit_checkpoints'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->tenantRls($t);
            $pg->immutable($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_checkpoints');
        Schema::dropIfExists('audit_events');
    }
};
