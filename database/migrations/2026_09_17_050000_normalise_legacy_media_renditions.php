<?php

use App\Support\Legacy\Renditions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Media rows catalogued by legacy:map before 17 September 2026 carry the
 * manifest's rendition shape, which broke the media library screen. Rewrite
 * them in the library's shape. An imported row with no derivative at all (a
 * photograph narrower than the smallest step) gets its original at its own
 * width, as an upload would. Rows already in the library's shape are left
 * alone, so this is safe to run on any database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_assets')->whereNotNull('renditions')->orderBy('id')->each(function ($row) {
            $renditions = json_decode((string) $row->renditions, true);

            $imported = $row->source_url !== null;

            if (! is_array($renditions)
                || ! (Renditions::isManifestShape($renditions) || ($imported && $renditions === []))) {
                return;
            }

            DB::table('media_assets')->where('id', $row->id)->update([
                'renditions' => json_encode(Renditions::fromManifest($renditions, $row->path, $row->width, $row->disk)),
            ]);
        });
    }

    public function down(): void
    {
        // The old shape was the defect. Nothing to put back.
    }
};
