<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery codes, and the login trail columns.
 *
 * Recovery codes are hashed, not encrypted: they are single-use secrets that
 * only ever need to be verified, never read back. Hashing means a database
 * copy does not hand anyone a working code, and it makes "show them again"
 * impossible by construction rather than by policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('last_seen_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_recovery_codes', 'last_login_at', 'last_login_ip']);
        });
    }
};
