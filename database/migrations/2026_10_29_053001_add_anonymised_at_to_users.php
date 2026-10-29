<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * F1: deleting a suspended account. The row stays, so every audit entry
 * and every record it wrote still points somewhere, but the person is gone:
 * name "Former user", no e-mail, no password, no country. anonymised_at
 * marks it and keeps it out of the users list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'anonymised_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('anonymised_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'anonymised_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('anonymised_at');
            });
        }
    }
};
