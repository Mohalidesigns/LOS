<?php

declare(strict_types=1);

use Fundly\Shared\Database\Migration;
use Illuminate\Support\Facades\DB;

/* WhatsApp as a notification channel, and the opt-in consent it requires (WhatsApp Business policy). */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table communications drop constraint communications_channel_chk');
        DB::statement("alter table communications add constraint communications_channel_chk check (channel in ('email', 'sms', 'whatsapp', 'in_app'))");
        DB::statement('alter table party_consents drop constraint party_consents_purpose_chk');
        DB::statement("alter table party_consents add constraint party_consents_purpose_chk check (purpose in ('data_processing', 'credit_bureau', 'credit_reporting', 'marketing', 'third_party_sharing', 'whatsapp_messaging'))");
    }

    public function down(): void
    {
        DB::statement('alter table party_consents drop constraint party_consents_purpose_chk');
        DB::statement("alter table party_consents add constraint party_consents_purpose_chk check (purpose in ('data_processing', 'credit_bureau', 'credit_reporting', 'marketing', 'third_party_sharing'))");
        DB::statement('alter table communications drop constraint communications_channel_chk');
        DB::statement("alter table communications add constraint communications_channel_chk check (channel in ('email', 'sms', 'in_app'))");
    }
};
