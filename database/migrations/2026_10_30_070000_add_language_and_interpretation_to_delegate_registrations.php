<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The language a delegate prefers to be written to in (en, fr, pt), and
 * the language they ask to have the proceedings interpreted into, if any.
 * A request, recorded: nothing here books an interpreter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegate_registrations', function (Blueprint $table) {
            $table->string('preferred_language', 2)->nullable()->after('delegate_role');
            $table->string('interpretation_into', 2)->nullable()->after('preferred_language');
        });
    }

    public function down(): void
    {
        Schema::table('delegate_registrations', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'interpretation_into']);
        });
    }
};
