<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INC-04, INC-03, INC-01: the publications list as the room will see it.
 *
 * 1. "Test of Publication" (Postal, Guideline, created during the demo on
 *    28 October 2026, no file) is removed with its translations.
 *
 * 2. The five legacy guidelines dated 1 January 2000, 2002 or 2003 lose the
 *    placeholder date. Their PDFs were read: the metadata dates are the
 *    dates the files were converted (2007 and 2021), not the dates of the
 *    documents, and the first pages carry no readable date, so the date is
 *    set to none rather than guessed. See stage notes publications-a2.md.
 *
 * 3. Drafts are not touched: none of the non-sample drafts has a file, so
 *    all stay hidden; the sample drafts stay hidden as sample data.
 *
 * Every row is matched by slug, and a date only when it still holds the
 * placeholder, so a date an editor has since entered is kept. Running it
 * twice changes nothing.
 */
return new class extends Migration
{
    private const TEST_SLUG = 'test-of-publication';

    /** slug => the placeholder date it carried */
    private const PLACEHOLDER_DATES = [
        'guidelines-on-interconnection-for-sadc-countries' => '2000-01-01',
        'guidelines-on-tariff-for-sadc-countries' => '2000-01-01',
        'policy-guidelines-in-licensing-of-telecommunications-in-sadc' => '2002-01-01',
        'wholesale-pricing-guidelines-for-ict-sector-for-sadc-edition-2002' => '2002-01-01',
        'guidelines-on-numbering-harmonisation-for-sadc-countries-edition-2003' => '2003-01-01',
    ];

    public function up(): void
    {
        $morph = 'App\Models\Publication';

        $test = DB::table('publications')->where('slug', self::TEST_SLUG)->first();
        if ($test !== null
            && ! DB::table('documents')->where('documentable_type', $morph)->where('documentable_id', $test->id)->exists()) {
            DB::table('publications')->where('supersedes_id', $test->id)->update(['supersedes_id' => null]);
            DB::table('translations')->where('translatable_type', $morph)->where('translatable_id', $test->id)->delete();
            if (DB::getSchemaBuilder()->hasTable('taggables')) {
                DB::table('taggables')->where('taggable_type', $morph)->where('taggable_id', $test->id)->delete();
            }
            DB::table('publications')->where('id', $test->id)->delete();
        }

        foreach (self::PLACEHOLDER_DATES as $slug => $placeholder) {
            DB::table('publications')
                ->where('slug', $slug)
                ->whereDate('published_on', $placeholder)
                ->update(['published_on' => null, 'updated_at' => now()]);
        }
    }

    /**
     * The placeholder dates come back where the date is still empty. The
     * test publication is not recreated: it was demo input, and putting it
     * back would put the defect back.
     */
    public function down(): void
    {
        foreach (self::PLACEHOLDER_DATES as $slug => $placeholder) {
            DB::table('publications')
                ->where('slug', $slug)
                ->whereNull('published_on')
                ->update(['published_on' => $placeholder]);
        }
    }
};
