<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Versioned configuration artefacts: draft → in_review → approved → active
 * (FR-TEN-009). A version's content is immutable once it leaves draft; the
 * database enforces this independently of the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('type', 64);
            $table->string('key', 96);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->uuid('active_version_id')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->unique(['tenant_id', 'type', 'key']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('config_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('artifact_id');
            $table->unsignedInteger('version_no');
            $table->string('status', 16);
            $table->jsonb('content');
            $table->char('content_hash', 64);
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->uuid('submitted_by')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->uuid('activation_change_request_id')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'artifact_id'])->references(['tenant_id', 'id'])->on('config_artifacts');
            $table->unique(['artifact_id', 'version_no']);
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("alter table config_versions add constraint config_versions_status_chk check (status in ('draft', 'in_review', 'approved', 'rejected', 'active', 'superseded'))");
        DB::statement("create unique index config_versions_one_active on config_versions (artifact_id) where status = 'active'");
        Schema::table('config_artifacts', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'active_version_id'])->references(['tenant_id', 'id'])->on('config_versions');
        });

        DB::statement(<<<'SQL'
            create or replace function fundly_config_version_guard() returns trigger
            language plpgsql as $$
            begin
                if tg_op = 'DELETE' then
                    if old.status <> 'draft' then
                        raise exception 'config version % is %, only drafts can be deleted', old.id, old.status
                            using errcode = 'insufficient_privilege';
                    end if;
                    return old;
                end if;
                if old.status <> 'draft' and (
                    new.content is distinct from old.content
                    or new.content_hash is distinct from old.content_hash
                    or new.version_no is distinct from old.version_no
                    or new.artifact_id is distinct from old.artifact_id
                    or new.created_by is distinct from old.created_by
                ) then
                    raise exception 'config version % is %, its content is immutable', old.id, old.status
                        using errcode = 'insufficient_privilege';
                end if;
                return new;
            end;
            $$
        SQL);
        DB::statement('create trigger config_versions_guard before update or delete on config_versions for each row execute function fundly_config_version_guard()');

        $pg = $this->pg();
        foreach (['config_artifacts', 'config_versions'] as $t) {
            $pg->grantCrud($t);
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::table('config_artifacts', fn (Blueprint $t) => $t->dropForeign(['tenant_id', 'active_version_id']));
        Schema::dropIfExists('config_versions');
        Schema::dropIfExists('config_artifacts');
    }
};
