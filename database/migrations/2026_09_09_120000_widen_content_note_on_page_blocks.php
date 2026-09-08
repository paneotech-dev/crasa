<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * content_note was varchar(300), which truncated the first brief long enough
 * to be useful. A note that says what the Secretariat must supply is worth
 * more than a note that fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->text('content_note')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->string('content_note', 300)->nullable()->change();
        });
    }
};
