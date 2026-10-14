<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * M10 Workflow (FR-WFL-001/003..012): the workflow version each application
 * is pinned to, stage tasks with assignment and SLA clocks, the assignment
 * history (who acted for whom), case notes, and saved inbox views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('workflow_key', 96);
            $table->string('source', 8);
            $table->uuid('version_id')->nullable();
            $table->unsignedInteger('version_no')->nullable();
            $table->timestampTz('pinned_at', 6);
            $table->uuid('migrated_from_version_id')->nullable();
            $table->string('migrated_by', 64)->nullable();
            $table->timestampTz('migrated_at', 6)->nullable();
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['tenant_id', 'application_id']);
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement("alter table workflow_instances add constraint workflow_instances_source_chk check (source in ('default', 'tenant'))");

        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('application_reference', 40);
            $table->string('applicant_name', 200)->nullable();
            $table->uuid('legal_entity_id');
            $table->uuid('org_unit_id');
            $table->uuid('product_id');
            $table->string('segment', 16);
            $table->string('stage_code', 48);
            $table->string('stage_name', 80);
            $table->string('canonical_status', 32);
            $table->string('permission', 64);
            $table->string('strategy', 16);
            $table->string('status', 16);
            $table->uuid('assignee_id')->nullable();
            $table->timestampTz('assigned_at', 6)->nullable();
            $table->unsignedInteger('warn_minutes')->nullable();
            $table->unsignedInteger('breach_minutes')->nullable();
            $table->timestampTz('warn_at', 6)->nullable();
            $table->timestampTz('breach_at', 6)->nullable();
            $table->string('sla_state', 16);
            $table->string('escalate_to', 64)->nullable();
            $table->timestampTz('warned_at', 6)->nullable();
            $table->timestampTz('breached_at', 6)->nullable();
            $table->timestampTz('paused_at', 6)->nullable();
            $table->unsignedInteger('paused_minutes')->default(0);
            $table->timestampTz('opened_at', 6);
            $table->timestampTz('closed_at', 6)->nullable();
            $table->string('closed_by', 64)->nullable();
            $table->string('close_reason', 64)->nullable();
            $table->timestampsTz(6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'assignee_id']);
            $table->index(['tenant_id', 'status', 'breach_at']);
            $table->index(['tenant_id', 'application_id']);
        });
        DB::statement("alter table workflow_tasks add constraint workflow_tasks_status_chk check (status in ('open', 'completed', 'cancelled'))");
        DB::statement("alter table workflow_tasks add constraint workflow_tasks_sla_chk check (sla_state in ('none', 'on_track', 'at_risk', 'breached'))");
        // one open task per application (P1: no parallel tracks)
        DB::statement("create unique index workflow_tasks_one_open on workflow_tasks (tenant_id, application_id) where status = 'open'");

        Schema::create('workflow_task_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('task_id');
            $table->string('kind', 16);
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->string('by_actor', 64);
            $table->uuid('on_behalf_of')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('at', 6);
            $table->foreign(['tenant_id', 'task_id'])->references(['tenant_id', 'id'])->on('workflow_tasks');
            $table->index(['tenant_id', 'task_id']);
        });
        DB::statement("alter table workflow_task_assignments add constraint workflow_task_assignments_kind_chk check (kind in ('routed', 'claimed', 'released', 'reassigned'))");

        Schema::create('case_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('author_id');
            $table->text('body');
            $table->string('visibility', 16);
            $table->jsonb('mentions');
            $table->timestampTz('created_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->index(['tenant_id', 'application_id', 'created_at']);
        });
        DB::statement("alter table case_notes add constraint case_notes_visibility_chk check (visibility in ('internal', 'customer'))");

        Schema::create('task_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('name', 60);
            $table->jsonb('filters');
            $table->timestampsTz(6);
            $table->unique(['tenant_id', 'user_id', 'name']);
        });

        $pg = $this->pg();
        $pg->grant('workflow_instances', ['select', 'insert', 'update']);
        $pg->grant('workflow_tasks', ['select', 'insert', 'update']);
        $pg->grant('task_views', ['select', 'insert', 'update', 'delete']);
        foreach (['workflow_task_assignments', 'case_notes'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->immutable($t);
        }
        foreach (['workflow_instances', 'workflow_tasks', 'workflow_task_assignments', 'case_notes', 'task_views'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        foreach (['task_views', 'case_notes', 'workflow_task_assignments', 'workflow_tasks', 'workflow_instances'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
