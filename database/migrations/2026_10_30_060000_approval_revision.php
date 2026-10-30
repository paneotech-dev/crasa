<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day 3 (editor flow): a change to a published item is held with its
 * approval request and written to the live row only when a moderator
 * approves it, so the public version stays online meanwhile. Null on every
 * request that concerns the item's own row, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approvals') && ! Schema::hasColumn('approvals', 'revision')) {
            Schema::table('approvals', function (Blueprint $table) {
                $table->json('revision')->nullable()->after('snapshot');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approvals') && Schema::hasColumn('approvals', 'revision')) {
            Schema::table('approvals', function (Blueprint $table) {
                $table->dropColumn('revision');
            });
        }
    }
};
