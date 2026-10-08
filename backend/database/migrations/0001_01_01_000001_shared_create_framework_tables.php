<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Framework infrastructure tables (cache, queue). They hold no tenant business
 * data, so they carry no tenant_id and no RLS; the runtime role gets DML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestampTz('failed_at', 6)->useCurrent();
            $table->index(['connection', 'queue', 'failed_at']);
        });

        $pg = $this->pg();
        foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $t) {
            $pg->grantCrud($t);
        }
        $pg->grantSequence('jobs_id_seq');
        $pg->grantSequence('failed_jobs_id_seq');
        // Readiness probe compares applied migrations with the release (read-only).
        $pg->grant('migrations', ['select']);
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
