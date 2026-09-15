<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * A member state contributing an instrument. RP-03 and RP-06, extranet
 * item 2.
 *
 * WHY THIS IS NOT THE EXISTING status COLUMN. `status` is the instrument's
 * standing in law: in force, amended, repealed, draft, proposed. That is a
 * fact about the instrument and it is what a regulator in another member
 * state reads. This is the standing of the contribution inside the
 * platform: whether the Secretariat has looked at it yet. A national act
 * that is in force can be a contribution the Secretariat has not validated,
 * and the two must not be able to overwrite each other.
 *
 * NULL MEANS THE SECRETARIAT ENTERED IT. Every instrument in the register
 * before this migration was typed in by the Secretariat and needs no
 * review, so the column is nullable rather than defaulted to draft, which
 * would take the whole register off the public site on deploy.
 *
 * THIS IS HOW THE REGISTER FILLS. From fourteen members, not from a
 * Secretariat of five people typing in fourteen countries' legislation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function ($table) {
            $table->string('contribution_state', 20)->nullable()->after('status');
            $table->foreignId('contributed_by')->nullable()->after('contribution_state')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('contributed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index('contribution_state');
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function ($table) {
            $table->dropIndex(['contribution_state']);
            $table->dropConstrainedForeignId('contributed_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['contribution_state', 'submitted_at', 'reviewed_at', 'review_note']);
        });
    }
};
