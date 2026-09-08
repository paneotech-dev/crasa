<?php

namespace Tests\Feature;

use App\Models\Translation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TranslatableRecord;
use Tests\TestCase;

/**
 * DATA_MODEL section 5, backed by the database.
 *
 * The resolver itself is unit tested against arrays in
 * TranslationResolverTest. These tests prove the trait resolves through the
 * same chain when the values come from the translations table, and that a
 * listing does not fire a query per field per record.
 */
class TranslationResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('translatable_records', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->timestamps();
        });
    }

    private function record(array $translations = []): TranslatableRecord
    {
        $record = TranslatableRecord::create(['slug' => 'roaming-guidelines']);

        foreach ($translations as $locale => $fields) {
            $record->setTranslations($locale, $fields);
        }

        return $record->fresh();
    }

    /* The chain ------------------------------------------------------------ */

    public function test_the_requested_language_is_returned_when_it_exists(): void
    {
        $record = $this->record([
            'en' => ['title' => 'SADC Roaming Guidelines'],
            'pt' => ['title' => 'Diretrizes de Roaming da SADC'],
        ]);

        $resolved = $record->translate('title', 'pt');

        $this->assertSame('Diretrizes de Roaming da SADC', $resolved->value);
        $this->assertSame('pt', $resolved->locale);
        $this->assertFalse($resolved->isFallback());
    }

    public function test_english_is_returned_when_the_requested_language_is_missing(): void
    {
        $record = $this->record(['en' => ['title' => 'SADC Roaming Guidelines']]);

        $resolved = $record->translate('title', 'pt');

        $this->assertSame('SADC Roaming Guidelines', $resolved->value);
        $this->assertSame('en', $resolved->locale);
        $this->assertTrue($resolved->isFallback());
    }

    public function test_the_active_locale_is_used_when_none_is_given(): void
    {
        $record = $this->record([
            'en' => ['title' => 'Spectrum Policy Framework'],
            'fr' => ['title' => 'Cadre de politique du spectre'],
        ]);

        app()->setLocale('fr');

        $this->assertSame('Cadre de politique du spectre', $record->translate('title')->value);
    }

    /* Missing means missing ------------------------------------------------ */

    public function test_an_empty_string_is_treated_as_missing(): void
    {
        $record = $this->record(['en' => ['title' => 'Model Law on Interconnection']]);

        // Written directly, bypassing setTranslation, which refuses blanks.
        Translation::create([
            'translatable_type' => $record->getMorphClass(),
            'translatable_id' => $record->getKey(),
            'locale' => 'fr',
            'field' => 'title',
            'value' => '',
        ]);

        $resolved = $record->fresh()->translate('title', 'fr');

        $this->assertSame('en', $resolved->locale);
        $this->assertTrue($resolved->isFallback());
    }

    public function test_a_whitespace_only_value_is_treated_as_missing(): void
    {
        $record = $this->record(['en' => ['title' => 'Model Law on Interconnection']]);

        Translation::create([
            'translatable_type' => $record->getMorphClass(),
            'translatable_id' => $record->getKey(),
            'locale' => 'fr',
            'field' => 'title',
            'value' => "   \n\t ",
        ]);

        $resolved = $record->fresh()->translate('title', 'fr');

        $this->assertSame('en', $resolved->locale);
    }

    public function test_setting_a_blank_value_removes_the_row_rather_than_storing_it(): void
    {
        $record = $this->record([
            'en' => ['title' => 'Model Law'],
            'fr' => ['title' => 'Loi type'],
        ]);

        $record->setTranslation('title', 'fr', '   ');

        $this->assertDatabaseMissing('translations', [
            'translatable_id' => $record->getKey(),
            'locale' => 'fr',
            'field' => 'title',
        ]);
    }

    public function test_a_field_missing_in_every_language_logs_and_returns_null(): void
    {
        Log::shouldReceive('warning')->once();

        $record = $this->record(['fr' => ['title' => 'Loi type']]);

        $this->assertNull($record->translate('summary', 'pt'));
    }

    /**
     * The rule that keeps a blank heading off the page: a missing field
     * renders as nothing, and the caller decides what to do about it.
     */
    public function test_a_missing_field_renders_as_nothing_not_as_an_empty_heading(): void
    {
        Log::shouldReceive('warning')->once();

        $record = $this->record(['en' => ['title' => 'Model Law']]);

        $this->assertSame('', $record->translated('body'));
    }

    /* Uniqueness ----------------------------------------------------------- */

    public function test_one_field_in_one_language_has_one_row(): void
    {
        $record = $this->record(['en' => ['title' => 'First']]);
        $record->setTranslation('title', 'en', 'Second');

        $this->assertSame(1, Translation::where('translatable_id', $record->getKey())
            ->where('locale', 'en')->where('field', 'title')->count());
        $this->assertSame('Second', $record->fresh()->translate('title', 'en')->value);
    }

    /* Reserved scope -------------------------------------------------------- */

    public function test_is_machine_draft_exists_and_stays_false(): void
    {
        $record = $this->record(['en' => ['title' => 'Model Law']]);

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $record->getKey(),
            'is_machine_draft' => false,
        ]);

        $this->assertSame(0, Translation::where('is_machine_draft', true)->count());
    }

    /* Completeness --------------------------------------------------------- */

    public function test_a_record_reports_which_languages_it_is_complete_in(): void
    {
        $record = $this->record([
            'en' => ['title' => 'T', 'summary' => 'S', 'body' => 'B'],
            'fr' => ['title' => 'T', 'summary' => 'S'],
        ]);

        $this->assertSame(['en'], $record->translatedLocales());
        $this->assertTrue($record->isTranslatedInto('en'));
        $this->assertFalse($record->isTranslatedInto('fr'));
    }

    /* Query count ---------------------------------------------------------- */

    /**
     * Fifty records with three fields each. Without eager loading this is
     * one query per field per record.
     */
    public function test_fifty_records_with_three_fields_resolve_in_two_queries(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $record = TranslatableRecord::create(['slug' => 'record-'.$i]);
            $record->setTranslations('en', ['title' => "Title $i", 'summary' => "Summary $i", 'body' => "Body $i"]);
            $record->setTranslations('pt', ['title' => "Titulo $i"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $records = TranslatableRecord::withTranslations()->get();

        foreach ($records as $record) {
            $record->translate('title', 'pt');
            $record->translate('summary', 'pt');
            $record->translate('body', 'pt');
        }

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(50, $records);
        $this->assertSame(2, $queries, 'Expected one query for the records and one for their translations.');
    }

    public function test_without_eager_loading_the_query_count_confirms_the_n_plus_one(): void
    {
        for ($i = 0; $i < 10; $i++) {
            TranslatableRecord::create(['slug' => 'record-'.$i])
                ->setTranslations('en', ['title' => "Title $i"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (TranslatableRecord::all() as $record) {
            $record->translate('title', 'en');
        }

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One for the records, one per record for its translations. This is
        // the shape withTranslations() exists to prevent.
        $this->assertSame(11, $queries);
    }
}
