<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secretariat announcements to the membership. RP-05, extranet item 10.
 *
 * WHY THIS IS NOT A NEWS ITEM. A news item is public and is written for a
 * reader who may be a journalist or a member of the public. An announcement
 * is members-only, it is addressed to the fourteen regulators, and it may be
 * narrowed to one sector or one committee. Publishing "the deadline for the
 * 2026 return moves to 30 November" on the public newsroom would be wrong
 * in both directions: it does not belong to the public, and it would not
 * reach the people it is for.
 *
 * TARGETING IS TWO NULLABLE COLUMNS, NOT A RULES ENGINE. An announcement is
 * for everybody, or for one sector, or for one committee. Those are the
 * three cases the Secretariat has asked for, and a general targeting system
 * for three cases is a system nobody can predict the behaviour of.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Null on both means every member state sees it.
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug', 200)->unique();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();

            // An announcement about a deadline stops being news the day
            // after it. Null means it stands until it is unpublished.
            $table->date('expires_on')->nullable();

            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
