<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to instruments, publications or committees.
 *
 * file_path is relative to the private disk, which is storage/app/private,
 * outside the web root. Documents are streamed by a controller that checks
 * role, country scope and clearance first. There is never a direct
 * filesystem URL, which is what makes the extranet requirement real rather
 * than a matter of nobody guessing the path.
 *
 * clearance replaces the is_public boolean from the original section 3.
 * A boolean cannot express four grades that screen 13 grants separately.
 * committee_id names the committee that owns a confidential document, which
 * is what answers who may open it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('file_path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            // The language the document itself is in. Attached PDFs stay in
            // their original language and carry a badge on the row.
            $table->char('locale', 5)->default('en');
            $table->string('thumbnail_path', 255)->nullable();
            $table->enum('clearance', ['public', 'member', 'confidential', 'secretariat'])
                ->default('member');
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id'], 'documents_morph');
            $table->index('clearance');
            $table->index('is_sample');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
