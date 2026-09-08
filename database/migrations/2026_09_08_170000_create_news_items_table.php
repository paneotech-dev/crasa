<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 6, plus a category column.
 *
 * Title, summary and body are translatable and live in the translations
 * table. hero_image_path is content supplied per article; where an article
 * has none, the listing falls back to a sector-differentiated treatment so
 * the grid never repeats one placeholder four times in a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->enum('category', ['news', 'tender', 'consultation', 'communique'])->default('news');
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('hero_image_path', 255)->nullable();
            $table->string('author_name', 120)->nullable();
            $table->enum('status', ['draft', 'review', 'published'])->default('draft');
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('category');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
