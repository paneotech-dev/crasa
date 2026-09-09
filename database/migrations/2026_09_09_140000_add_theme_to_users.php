<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The back office surface set, per account.
 *
 * Screen 09: "The Secretariat sets it per account, and it follows the
 * operating system by default." Three values rather than two, because
 * "follow the operating system" is a different answer from "light" and a
 * user who has never chosen must not be recorded as having chosen.
 *
 * A string with a check rather than a SET: the SET type does not exist in
 * SQLite and the suite runs on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 16)->default('system')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
