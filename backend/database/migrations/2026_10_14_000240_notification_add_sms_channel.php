<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* SMS as a notification channel (FR-NTF-001): the gateway's message id is kept for delivery queries. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table communications drop constraint communications_channel_chk');
        DB::statement("alter table communications add constraint communications_channel_chk check (channel in ('email', 'sms', 'in_app'))");
        Schema::table('communications', function (Blueprint $table) {
            $table->string('provider_ref', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn('provider_ref');
        });
        DB::statement('alter table communications drop constraint communications_channel_chk');
        DB::statement("alter table communications add constraint communications_channel_chk check (channel in ('email', 'in_app'))");
    }
};
