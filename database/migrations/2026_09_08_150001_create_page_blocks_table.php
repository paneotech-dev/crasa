<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translatable text within a block goes in the translations table against
 * the block, not into settings. That is what keeps the translation editor
 * uniform: one editor handles instrument titles, publication summaries and
 * homepage copy the same way.
 *
 * settings holds configuration only, and is validated server-side against
 * BlockType::settingsRules() on every write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('block_type', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('settings')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['page_id', 'position']);
            $table->index('block_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
