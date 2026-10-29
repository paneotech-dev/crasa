<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The regulator a delegate attends for. The country alone does not say it
 * where a member state has two regulators.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegate_registrations', function (Blueprint $table) {
            $table->foreignId('authority_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delegate_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authority_id');
        });
    }
};
