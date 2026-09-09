<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A page's history, on the same shape as instrument_versions.
 *
 * The legal pages are the reason this exists. A privacy notice and a terms
 * of use are documents somebody may have relied on, and "what did this page
 * say on the day I read it" is a question a regulator is entitled to ask.
 * The answer cannot be reconstructed from an audit log entry that records
 * only that a block changed.
 *
 * payload is the whole page at that moment: status, the page's own
 * translations, and every block with its type, position, visibility,
 * settings and translations in all three languages. A version is written
 * after a change, on the same terms as InstrumentVersioning, so version 1 is
 * the page as it stood before its first tracked edit and every later version
 * is the state that edit produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 500);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['page_id', 'version_number']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
