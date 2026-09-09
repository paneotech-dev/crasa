<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who confirmed a member state's own record, and when.
 *
 * All fourteen authorities are flagged is_sample, which is to say no member
 * state has confirmed its own regulator's name, acronym or mandate in
 * writing. Fourteen written confirmations from fourteen national regulators
 * inside fourteen days is not realistic — but the fourteen delegates who can
 * confirm will be in one room in Lilongwe on 23 September.
 *
 * is_sample already says whether a record is demonstration data. It does not
 * say who vouched for it, and for a record a regulator will be asked to
 * stand behind, "confirmed" without "by whom" is not a confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('is_sample');
            $table->foreignId('confirmed_by_id')->nullable()->after('confirmed_at')
                ->constrained('users')->nullOnDelete();
            // The delegate who confirmed it, in their own words. The
            // account is the Secretariat operator driving the session; the
            // person vouching is the delegate in the room, and those are
            // rarely the same person.
            $table->string('confirmed_by_name', 160)->nullable()->after('confirmed_by_id');

            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('authorities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by_id');
            $table->dropColumn(['confirmed_at', 'confirmed_by_name']);
        });
    }
};
