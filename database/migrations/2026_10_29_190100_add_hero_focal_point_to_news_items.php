<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a news photograph is cropped around, as percentages from the left
 * and from the top. The public card and the article hero read it through
 * object-position. Null keeps the framing each surface used before: the
 * card centred, the article hero at 50% 30%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('hero_focal_x')->nullable()->after('hero_image_path');
            $table->unsignedTinyInteger('hero_focal_y')->nullable()->after('hero_focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropColumn(['hero_focal_x', 'hero_focal_y']);
        });
    }
};
