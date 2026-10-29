<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Homepage, 29 October 2026.
 *
 * The comparator appeared three times on the landing page. The featured
 * comparison block stays; the statement band (the "licence condition"
 * block with the indicators-reported grid) is hidden.
 *
 * The "What we do" band carried four bare figures. It now carries the four
 * lines of work, each with its figure as support. The wording is the
 * pillars block's own, copied onto the key figures block as item_N_heading
 * and item_N_body, and the pillars block is hidden so the same four
 * headings do not appear twice on one page.
 *
 * Blocks are hidden rather than deleted: an editor can show them again from
 * the back office, and down() does exactly that.
 */
return new class extends Migration
{
    private const TYPE = 'App\\Models\\PageBlock';

    public function up(): void
    {
        $home = DB::table('pages')->where('slug', 'home')->value('id');
        if ($home === null) {
            return;
        }

        DB::table('page_blocks')
            ->where('page_id', $home)
            ->whereIn('block_type', ['statement_band', 'pillars'])
            ->update(['is_visible' => false, 'updated_at' => now()]);

        $figures = DB::table('page_blocks')->where('page_id', $home)->where('block_type', 'key_figures')->first();
        $pillars = DB::table('page_blocks')->where('page_id', $home)->where('block_type', 'pillars')->first();

        if ($figures === null) {
            return;
        }

        // The member regulators figure goes to the members page.
        $settings = json_decode($figures->settings ?? '[]', true) ?: [];
        if (isset($settings['items'][0])) {
            $settings['items'][0]['link'] = 'members';
            DB::table('page_blocks')->where('id', $figures->id)->update([
                'settings' => json_encode($settings),
                'updated_at' => now(),
            ]);
        }

        if ($pillars === null) {
            return;
        }

        // Filtered here rather than with LIKE, whose escape character is
        // not the same on MariaDB and SQLite.
        $rows = DB::table('translations')
            ->where('translatable_type', self::TYPE)
            ->where('translatable_id', $pillars->id)
            ->get()
            ->filter(fn ($row) => self::isStatementField($row->field));

        foreach ($rows as $row) {
            $exists = DB::table('translations')
                ->where('translatable_type', self::TYPE)
                ->where('translatable_id', $figures->id)
                ->where('locale', $row->locale)
                ->where('field', $row->field)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('translations')->insert([
                'translatable_type' => self::TYPE,
                'translatable_id' => $figures->id,
                'locale' => $row->locale,
                'field' => $row->field,
                'value' => $row->value,
                'translated_by' => $row->translated_by,
                'is_machine_draft' => $row->is_machine_draft,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $home = DB::table('pages')->where('slug', 'home')->value('id');
        if ($home === null) {
            return;
        }

        DB::table('page_blocks')
            ->where('page_id', $home)
            ->whereIn('block_type', ['statement_band', 'pillars'])
            ->update(['is_visible' => true, 'updated_at' => now()]);

        $figures = DB::table('page_blocks')->where('page_id', $home)->where('block_type', 'key_figures')->value('id');
        if ($figures !== null) {
            $ids = DB::table('translations')
                ->where('translatable_type', self::TYPE)
                ->where('translatable_id', $figures)
                ->get(['id', 'field'])
                ->filter(fn ($row) => self::isStatementField($row->field))
                ->pluck('id');

            DB::table('translations')->whereIn('id', $ids->all())->delete();
        }
    }

    private static function isStatementField(string $field): bool
    {
        return preg_match('/^item_\d+_(heading|body)$/', $field) === 1;
    }
};
