<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, WS-12 and the no-content-loss clause.
 *
 * WHY THIS TABLE EXISTS AT ALL. The legacy site carries two project
 * sections, current and past, behind a top-level menu item, and the new
 * information architecture in WS-03 lists neither. That was recorded in
 * LEGACY_INVENTORY as the first of two findings that change scope. Four
 * projects are live on the legacy site, each with its own page, its own
 * photograph and in three cases its own document.
 *
 * A project is not a publication and not a news item. It runs over a
 * period, it has a partner, and it moves from current to past without its
 * page changing identity. Modelling it as either of the others loses the
 * period and the state, which is what the two menu sections are.
 *
 * Title, summary and body are translatable; the period, the partner and
 * the state are not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 200)->unique();

            // Current or past. This is the legacy menu's own division and
            // it is the division the Secretariat maintains.
            $table->string('state', 20)->default('current');

            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('partner', 200)->nullable();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->string('hero_image_path', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'state']);
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
