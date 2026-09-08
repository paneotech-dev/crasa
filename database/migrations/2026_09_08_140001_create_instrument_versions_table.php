<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every edit creates a version. An instrument's text is never updated in
 * place, so the text as it stood on any date is always retrievable.
 *
 * payload is a full snapshot of the translatable fields at that version, in
 * every language. The diff view reads two payloads and renders the
 * difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 500);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['instrument_id', 'version_number']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_versions');
    }
};
