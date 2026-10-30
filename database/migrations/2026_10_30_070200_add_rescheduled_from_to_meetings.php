<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The first day a meeting had before it was moved to a later date (or
 * moved while postponed). Set by Meeting when the date changes; the public
 * page then reads "Postponed, new date ..." (client item 1e).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->date('rescheduled_from')->nullable()->after('status_note');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('rescheduled_from');
        });
    }
};
