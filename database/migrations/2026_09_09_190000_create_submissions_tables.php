<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The e-Form workflow, RP-09, DATA_MODEL section 4.
 *
 * WHY A SUBMISSION IS NOT AN INDICATOR VALUE. A member state's return is a
 * working document: it is drafted, sent, questioned and corrected, and
 * while all that is happening it must not appear anywhere the public or
 * another member state can read it. indicator_values is the published
 * regional dataset that the comparator and the completeness matrix read,
 * and it carries no country scope precisely because everything in it is
 * meant to be read by everyone.
 *
 * So the two are separate tables and validation is the moment a figure
 * crosses from one to the other. That is what keeps the public comparator
 * a simple, safe read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 20);

            // A string with the states enforced in the enum, not a SET: the
            // SET type does not exist in SQLite and the suite runs on it.
            $table->string('status', 20)->default('draft');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            // One return per member state per period. A second one would
            // make "the 2026 return from Zambia" ambiguous.
            $table->unique(['country_id', 'period']);
            $table->index('status');
        });

        Schema::create('submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->decimal('value_numeric', 20, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->char('currency', 3)->nullable();

            /*
             * Screen 06 puts a source field under every figure and marks it
             * red when it is empty: "Source note required". A figure whose
             * origin nobody recorded cannot be defended when a member state
             * queries it, which is the whole reason this platform exists.
             */
            $table->string('source_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'indicator_id']);
        });

        Schema::create('submission_field_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            /*
             * Against a field, not against the submission. Screen 06:
             * "Comments are attached to specific fields, not to the
             * submission as a whole, so you can go straight to what needs
             * changing."
             */
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['submission_id', 'resolved']);
        });

        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();

            // Append only. There is no updated_at because a row here is
            // never updated.
            $table->timestamp('created_at')->nullable();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('submission_field_comments');
        Schema::dropIfExists('submission_values');
        Schema::dropIfExists('submissions');
    }
};
