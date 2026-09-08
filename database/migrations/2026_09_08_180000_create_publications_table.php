<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 6.
 *
 * slug is permanent, like an instrument's: a publication is cited, and a
 * citation has to keep resolving.
 *
 * supersedes_id points backwards, at what this publication replaced. The
 * reverse relation gives the reader what replaced it, so a superseded
 * document is never a dead end.
 *
 * is_public governs whether the record is listed at all. The clearance of
 * the files attached to it is on the documents themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->enum('publication_type', [
                'annual_report', 'guideline', 'study', 'position_paper', 'communique', 'minutes',
            ]);
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            // Null means regional rather than belonging to one member state.
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->date('published_on');
            $table->foreignId('supersedes_id')->nullable()->constrained('publications')->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['publication_type', 'published_on']);
            $table->index('published_on');
            $table->index('is_public');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
