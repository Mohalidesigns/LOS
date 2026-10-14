<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M16 Notifications: the communication log (FR-NTF-005). One row per message
 * per recipient per channel: what was sent, to whom, when, through which
 * channel, from which template version, and its delivery status. The dedupe
 * key suppresses a second copy of the same message (FR-NTF-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id')->nullable();
            $table->string('event', 64);
            $table->string('channel', 16);
            $table->string('audience', 16);
            $table->uuid('recipient_user_id')->nullable();
            $table->uuid('recipient_party_id')->nullable();
            $table->string('recipient_hint', 200)->nullable();
            $table->string('template_source', 16);
            $table->uuid('template_version_id')->nullable();
            $table->char('language', 2);
            $table->string('subject', 300);
            $table->text('body');
            $table->string('link', 300)->nullable();
            $table->jsonb('attachments');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->string('dedupe_key', 300);
            $table->timestampTz('created_at', 6);
            $table->timestampTz('sent_at', 6)->nullable();
            $table->timestampTz('read_at', 6)->nullable();
            $table->unique(['tenant_id', 'dedupe_key']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
            $table->index(['tenant_id', 'recipient_user_id', 'read_at']);
        });
        DB::statement("alter table communications add constraint communications_channel_chk check (channel in ('email', 'in_app'))");
        DB::statement("alter table communications add constraint communications_status_chk check (status in ('queued', 'sent', 'failed', 'delivered', 'read'))");

        $pg = $this->pg();
        $pg->grant('communications', ['select', 'insert', 'update']);
        $pg->tenantRls('communications');
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
