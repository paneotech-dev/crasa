<?php

use App\Support\Documents\DemonstrationFileNames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The demonstration folders showed their papers as "sample-validated-
 * returns.pdf" and the like. This renames the rows only: the display name
 * of each demonstration paper (is_sample, in a folder, named after its
 * seeder slug). The stored file, its path and every real CRASA document
 * are left exactly as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('documents')
            ->whereNotNull('folder_id')
            ->where('is_sample', true)
            ->where('original_filename', 'like', 'sample-%')
            ->get(['id', 'folder_id', 'file_path', 'original_filename', 'created_at']);

        foreach ($rows as $row) {
            $slug = pathinfo($row->original_filename, PATHINFO_FILENAME);

            // Only a paper whose path still is that slug: a renamed or a
            // real file is never touched.
            if (! in_array($slug, DemonstrationFileNames::SLUGS, true) || basename($row->file_path) !== $slug.'.pdf') {
                continue;
            }

            $folder = DB::table('folders')->where('id', $row->folder_id)->first(['country_id', 'committee_id']);
            $country = $folder?->country_id ? DB::table('countries')->where('id', $folder->country_id)->value('name') : null;
            $authority = $folder?->country_id ? DB::table('authorities')->where('country_id', $folder->country_id)->orderBy('id')->value('acronym') : null;
            $committee = $folder?->committee_id ? DB::table('committees')->where('id', $folder->committee_id)->value('name') : null;
            $year = (int) substr((string) ($row->created_at ?? now()), 0, 4);

            $name = DemonstrationFileNames::for($slug, $authority, is_string($country) ? $country : null, is_string($committee) ? $committee : null, $year);

            if ($name === null) {
                continue;
            }

            DB::table('documents')->where('id', $row->id)->update(['original_filename' => $name]);
            DB::table('document_versions')->where('document_id', $row->id)->where('file_path', $row->file_path)->update(['original_filename' => $name]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('documents')
            ->whereNotNull('folder_id')
            ->where('is_sample', true)
            ->where('file_path', 'like', '%/sample-%')
            ->where('original_filename', 'not like', 'sample-%')
            ->get(['id', 'file_path']);

        foreach ($rows as $row) {
            if (in_array(pathinfo($row->file_path, PATHINFO_FILENAME), DemonstrationFileNames::SLUGS, true)) {
                DB::table('documents')->where('id', $row->id)->update(['original_filename' => basename($row->file_path)]);
                DB::table('document_versions')->where('document_id', $row->id)->where('file_path', $row->file_path)->update(['original_filename' => basename($row->file_path)]);
            }
        }
    }
};
