<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library, CM-03.
 *
 * Screen 14: "Nothing is hotlinked. Every asset is stored by the platform,
 * with renditions generated on upload." The whole table exists to make that
 * sentence enforceable.
 *
 * Alt text and caption are not here. They are per-language and they live in
 * the translations table with everything else that is, which is what makes
 * "this asset cannot appear on the Portuguese site until its Portuguese alt
 * text is filled" a query rather than a convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();

            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            /*
             * The SHA-256 of the stored file. Two uploads of the same
             * photograph are the same asset, and a library that holds the
             * AGM plenary shot four times under four names is a library
             * nobody trusts.
             */
            $table->string('checksum', 64)->unique();

            /*
             * The generated renditions, as width => {path, bytes}. A column
             * rather than a table: they are derived, they are rebuilt by
             * re-running the generator, and nothing ever joins on them.
             */
            $table->json('renditions')->nullable();

            /*
             * Rights. Screen 14: "Every asset keeps its source and its
             * licence so a COMESA query can be answered in seconds." A
             * donor-funded platform gets asked.
             */
            $table->string('rights_holder')->nullable();
            $table->string('licence')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('rights_confirmed_at')->nullable();
            $table->foreignId('rights_confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_sample')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
