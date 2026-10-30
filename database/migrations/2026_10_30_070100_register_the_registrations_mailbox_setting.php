<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The CRASA mailbox that receives a copy of every delegate registration.
 * Registered empty: while it is empty the copy goes to the Secretariat's
 * contact address (SiteContact::email()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('site_settings')->where('key', 'registrations_mailbox')->exists()) {
            return;
        }

        DB::table('site_settings')->insert([
            'key' => 'registrations_mailbox',
            'value' => null,
            'type' => 'email',
            'group' => 'registrations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'registrations_mailbox')->delete();
    }
};
