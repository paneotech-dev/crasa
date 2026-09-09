<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generated reports, RP-12.
 *
 * "Generate, view, store and print." Stored, because a brief that only
 * exists in the browser tab that made it cannot be found again, and the
 * question six months later is always "where is the one we sent in March".
 *
 * The report keeps its parameters rather than its rendered output. A brief
 * regenerated from the same subject shows today's figures, which is what
 * anyone reopening it wants; the stored PDF beside it is the copy that was
 * actually circulated, and the two are deliberately different things.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // country or indicator. A brief is about one or the other.
            $table->string('subject_type', 20);
            $table->string('subject_key', 120);

            $table->string('title');
            $table->string('period', 20)->nullable();
            $table->string('locale', 5)->default('en');

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            // The rendered copy, once someone exports one. Null until then.
            $table->string('pdf_path')->nullable();
            $table->timestamp('exported_at')->nullable();

            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index(['subject_type', 'subject_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
