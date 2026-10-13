<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * M15 Handover (FR-HND-001/003/004): the package handed to servicing when a
 * facility is booked (stored as the exact JSON that was hashed), servicing's
 * acknowledgements, and the origination record's retention. Packages and
 * acknowledgements are evidence and append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handover_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('disbursement_id');
            $table->text('package_json');
            $table->char('sha256', 64);
            $table->uuid('servicing_org_unit_id');
            $table->uuid('webhook_event_id')->nullable();
            $table->string('created_by', 64);
            $table->timestampTz('created_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique('disbursement_id');
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'application_id']);
        });

        Schema::create('handover_acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('package_id');
            $table->string('acknowledged_by', 64);
            $table->text('note');
            $table->timestampTz('acknowledged_at', 6);
            $table->foreign(['tenant_id', 'package_id'])->references(['tenant_id', 'id'])->on('handover_packages');
        });

        Schema::create('retention_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->string('category', 48);
            $table->string('trigger', 32);
            $table->date('starts_on');
            $table->date('retain_until');
            $table->boolean('legal_hold')->default(false);
            $table->string('basis', 300);
            $table->timestampTz('created_at', 6);
            $table->foreign(['tenant_id', 'application_id'])->references(['tenant_id', 'id'])->on('applications');
            $table->unique(['application_id', 'category']);
        });

        $pg = $this->pg();
        foreach (['handover_packages', 'handover_acknowledgements'] as $t) {
            $pg->grantAppendOnly($t);
            $pg->immutable($t);
        }
        // legal hold is set and lifted later (FR-CMP-034); records are never deleted
        $pg->grant('retention_records', ['select', 'insert', 'update']);
        foreach (['handover_packages', 'handover_acknowledgements', 'retention_records'] as $t) {
            $pg->tenantRls($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_records');
        Schema::dropIfExists('handover_acknowledgements');
        Schema::dropIfExists('handover_packages');
    }
};
