<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member state proposing a change to its own authority record.
 * Extranet item 3.
 *
 * WHY A PROPOSAL TABLE RATHER THAN EDITING THE RECORD. The authority record
 * is what the public members page shows and what a ministry cites. A
 * representative editing it directly would mean the name of a national
 * regulator on a treaty body's public site could change without anybody at
 * the Secretariat seeing it. So the representative writes a proposal and
 * the Secretariat applies it.
 *
 * THE PAYLOAD IS JSON, DELIBERATELY. A proposal is a whole record as the
 * member state would have it: name, acronym, website, year, mandate,
 * sectors, committee seats. Shadow columns for each would double the schema
 * and would have to be kept in step with the authority table for ever.
 *
 * THE SAME WORKFLOW AS THE LILONGWE CONFIRMATION. Approving a proposal runs
 * the same apply step the confirmation screen runs, and records the same
 * confirmation fields, because they are the same act: a member state
 * standing behind its own record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_changes', function (Blueprint $table) {
            $table->id();

            /*
             * Nullable, because a member state whose authority record does
             * not exist yet is exactly the state most in need of proposing
             * one. Creating a stub authority to hang the proposal off would
             * put an unreviewed name on the public members page, which is
             * the thing this table exists to prevent.
             */
            $table->foreignId('authority_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('payload');
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_changes');
    }
};
