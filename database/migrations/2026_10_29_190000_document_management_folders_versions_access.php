<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Document management in the member area and the back office.
 *
 * A folder gains a description and its own access rules (who sees it, who
 * uploads, who validates). Null rules mean "as the folder above", so a
 * subfolder inherits until somebody changes it.
 *
 * A file gains its versions (every replacement is kept, with who and when),
 * the text of a PDF for search, a share token for a stable link that still
 * checks access when it is opened, who sent it to the recycle bin, and who
 * validated it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->json('access')->nullable()->after('clearance');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('size_bytes');
            $table->longText('search_text')->nullable()->after('version');
            $table->string('share_token', 40)->nullable()->unique()->after('search_text');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('share_token');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'version']);
        });

        // Every file already in a folder becomes version 1 of itself, so
        // its history starts with the upload rather than with nothing.
        // Rows only: no file is read, moved or written.
        DB::table('documents')->whereNotNull('folder_id')->orderBy('id')->each(function ($row): void {
            DB::table('document_versions')->insert([
                'document_id' => $row->id,
                'version' => 1,
                'file_path' => $row->file_path,
                'original_filename' => $row->original_filename,
                'mime_type' => $row->mime_type,
                'size_bytes' => $row->size_bytes,
                'uploaded_by' => $row->uploaded_by,
                'created_at' => $row->created_at,
            ]);

            DB::table('documents')->where('id', $row->id)->update(['share_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropUnique(['share_token']);
            $table->dropColumn(['version', 'search_text', 'share_token', 'validated_at']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['description', 'access']);
        });
    }
};
