<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRASA's committees. sector_id is nullable because several are cross-sector.
 *
 * is_sample is not in the specification's column list for this table. It is
 * added here because CLAUDE.md requires that every table holding content
 * which could be demonstration data carries the flag, and the seeded
 * mandates, chairs and membership are exactly that. Without it the go-live
 * purge cannot reach them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 200);
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->text('mandate');
            $table->foreignId('chair_authority_id')->nullable()->constrained('authorities')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('is_active');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
