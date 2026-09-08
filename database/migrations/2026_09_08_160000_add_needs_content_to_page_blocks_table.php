<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a block whose copy is placeholder pending real text from the
 * Secretariat.
 *
 * The alternative is a comment in a seeder that nobody reads, and copy that
 * quietly ships because it reads plausibly. A flagged block is listed by
 * `php artisan content:placeholders`, which is the list that goes to CRASA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->boolean('needs_content')->default(false)->after('is_sample');
            $table->string('content_note', 300)->nullable()->after('needs_content');

            $table->index('needs_content');
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropIndex(['needs_content']);
            $table->dropColumn(['needs_content', 'content_note']);
        });
    }
};
