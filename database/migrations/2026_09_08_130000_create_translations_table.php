<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA_MODEL section 5. One polymorphic table, three locales.
 *
 * Never title_fr and title_pt columns: a per-language column set means every
 * new translatable field is a migration on every table, and the translation
 * editor has to know the shape of each one.
 *
 * is_machine_draft is reserved. AI translation is out of scope, the column
 * stays false, and nothing in this codebase writes to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->char('locale', 2);
            $table->string('field', 60);
            $table->longText('value')->nullable();
            $table->foreignId('translated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_machine_draft')->default(false);
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'translations_unique'
            );

            // The eager-loading path: every translation for a set of records
            // in one query.
            $table->index(['translatable_type', 'translatable_id'], 'translations_morph');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
