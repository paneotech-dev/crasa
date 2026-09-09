<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each account has already been shown.
 *
 * Per account rather than per browser. A Secretariat officer who signs in
 * from the office and from a laptop is one person who has read the
 * introduction once, and localStorage would show it to them again.
 *
 * A JSON list of dismissed keys rather than a single boolean, so a hint
 * added later can appear for people who joined before it existed without
 * re-showing everything they have already dismissed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dismissed_hints')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dismissed_hints');
        });
    }
};
