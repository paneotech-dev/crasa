<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demonstration accounts, flagged like every other demonstration row.
 *
 * CLAUDE.md requires the flag on every table holding content that could be
 * demonstration data, and an account created so somebody can log in as a
 * Botswana representative and look around is exactly that. Without the flag
 * the go-live purge cannot reach it, and six accounts with generated
 * passwords survive into production because nobody remembered them.
 *
 * There is still no seeder that creates accounts. Real accounts are made by
 * the Secretariat at handover, through user:create.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('status');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_sample']);
            $table->dropColumn('is_sample');
        });
    }
};
