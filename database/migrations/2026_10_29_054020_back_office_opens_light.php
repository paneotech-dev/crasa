<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * F11: the back office opens in its light, green and white look for the
 * workshop. Accounts still on "system" (the old default, never chosen in
 * the theme control by most) move to "light". The theme control in the top
 * bar still offers "System", which follows the device, and "Dark".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'theme')) {
            return;
        }

        DB::table('users')->where('theme', 'system')->update(['theme' => 'light']);
    }

    public function down(): void
    {
        // The previous default. Every account on "light" goes back to
        // following the device, which is what they had before.
        if (Schema::hasColumn('users', 'theme')) {
            DB::table('users')->where('theme', 'light')->update(['theme' => 'system']);
        }
    }
};
