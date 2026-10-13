<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M12 Offer & execution: offers (one per issue or re-issue), their ordered
 * signatories, deliveries, and executed agreements (FR-OFR-001..010).
 * Agreements are evidence and append-only; offers move through statuses but
 * are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 16);
            $table->string('template_key', 96);
            $table->uuid('template_version_id');
            $table->unsignedInteger('template_version_no');
            $table->string('execution_method', 16);
            $table->uuid('approval_request_id');
            $table->jsonb('terms');
            $table->jsonb('key_facts');
            $table->jsonb('schedule');
            $table->string('schedule_source', 96);
            $table->uuid('document_id');
            $table->uuid('document_version_id');
            $table->char('document_sha256', 64);
            $table->string('issued_by', 64);
            $table->timestampTz('issued_at', 6);
            $table->timestampTz('valid_until', 6);
            $table->uuid('reissue_of')->nullable();
            $table->unsignedSmallInteger('reissue_count');
            $table->string('decision_reason_code', 48)->nullable();
            $table->text('decision_note')->nullable();
            $table->string('decided_by', 64)->nullable();
            $table->timestampTz('decided_at', 6)->nullable();
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->foreign(['tenant_id', 'template_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
            $table->unique(['application_id', 'sequence']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("alter table offers add constraint offers_status_chk check (status in ('issued', 'accepted', 'rejected', 'negotiating', 'expired', 'superseded'))");
        DB::statement("create unique index offers_one_live on offers (application_id) where status = 'issued'");

        Schema::create('offer_signatories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('offer_id');
            $table->unsignedSmallInteger('position');
            $table->string('role', 16);
            $table->uuid('party_id')->nullable();
            $table->string('display_name', 300);
            $table->string('method', 16)->nullable();
            $table->timestampTz('signed_at', 6)->nullable();
            $table->string('witness_name', 200)->nullable();
            $table->string('place', 200)->nullable();
            $table->string('recorded_by', 64)->nullable();
            $table->timestampTz('recorded_at', 6)->nullable();
            $table->foreign(['tenant_id', 'offer_id'])->references(['tenant_id', 'id'])->on('offers');
            $table->unique(['offer_id', 'position']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('offer_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('offer_id');
            $table->string('channel', 16);
            $table->uuid('party_id')->nullable();
            $table->string('recipient_hint', 200)->nullable();
            $table->string('status', 16);
            $table->string('failure', 300)->nullable();
            $table->string('requested_by', 64);
            $table->timestampTz('requested_at', 6);
            $table->timestampTz('completed_at', 6)->nullable();
            $table->foreign(['tenant_id', 'offer_id'])->references(['tenant_id', 'id'])->on('offers');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'offer_id']);
        });
        DB::statement("alter table offer_deliveries add constraint offer_deliveries_channel_chk check (channel in ('email', 'print'))");
        DB::statement("alter table offer_deliveries add constraint offer_deliveries_status_chk check (status in ('queued', 'sent', 'failed', 'recorded'))");

        Schema::create('agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('offer_id');
            $table->uuid('template_version_id');
            $table->string('execution_method', 16);
            $table->uuid('document_id');
            $table->uuid('document_version_id');
            $table->char('sha256', 64);
            $table->char('offer_document_sha256', 64);
            $table->jsonb('signatures');
            $table->string('recorded_by', 64);
            $table->timestampTz('executed_at', 6);
            $table->foreign(['tenant_id', 'offer_id'])->references(['tenant_id', 'id'])->on('offers');
            $table->foreign(['tenant_id', 'template_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
            $table->unique('offer_id');
            $table->index(['tenant_id', 'application_id']);
        });

        $pg = $this->pg();
        foreach (['offers', 'offer_signatories', 'offer_deliveries'] as $t) {
            $pg->grant($t, ['select', 'insert', 'update']);
        }
        $pg->grantAppendOnly('agreements');
        $pg->immutable('agreements');
        foreach (['offers', 'offer_signatories', 'offer_deliveries', 'agreements'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
        Schema::dropIfExists('offer_deliveries');
        Schema::dropIfExists('offer_signatories');
        Schema::dropIfExists('offers');
    }
};
