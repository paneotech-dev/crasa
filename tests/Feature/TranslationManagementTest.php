<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Translation;
use App\Models\User;
use App\Support\Locales;
use App\Support\TranslationCoverage;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Translation management, CM-04, against screen 05.
 *
 * The rule these exist to hold: nothing in this platform ever creates a
 * machine draft. Screen 05 shows the state because imported content may
 * arrive carrying it, and an editor has to be able to approve or rewrite
 * one. Nothing generates one, and no English string is ever copied into a
 * French or Portuguese row to make a coverage bar look finished.
 */
class TranslationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->editor = User::factory()->editor()->create();

        $this->page = Page::create(['slug' => 'roaming', 'template' => 'standard', 'status' => 'published', 'published_at' => now()]);
        $this->page->setTranslations('en', ['title' => 'SADC roaming guidelines', 'meta_description' => 'The regional framework.']);
    }

    /* The rule --------------------------------------------------------------- */

    public function test_only_the_approval_drafts_ever_set_is_machine_draft_to_true(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            $contents = $file->getContents();

            // The column may be read and it may be cleared. Since item 1b
            // (Day 3) one place sets it: the machine drafts written when an
            // item is approved. Nothing else manufactures a machine draft.
            if ($file->getRelativePathname() === 'Services/MachineTranslation/MachineDrafts.php') {
                continue;
            }

            if (preg_match('/is_machine_draft[\'"]?\s*(=>|,)\s*(true|1\b)/', $contents)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'These files set is_machine_draft: '.implode(', ', $offenders));
    }

    public function test_saving_a_translation_records_a_person_and_not_a_machine(): void
    {
        $this->actingAs($this->editor)
            ->put('/en/admin/translations/pages/'.$this->page->id, [
                'fr' => ['title' => 'Lignes directrices sur itinerance'],
            ])
            ->assertRedirect();

        $translation = Translation::where('locale', 'fr')->where('field', 'title')->sole();

        $this->assertFalse($translation->is_machine_draft);
        $this->assertSame($this->editor->id, $translation->translated_by);
    }

    /* Coverage --------------------------------------------------------------- */

    public function test_coverage_counts_approved_fields_only(): void
    {
        $this->page->setTranslations('fr', ['title' => 'Titre', 'meta_description' => 'Description']);

        // A machine draft, as imported content would carry it.
        Translation::where('locale', 'fr')->where('field', 'title')->update(['is_machine_draft' => true]);

        $this->page->refresh()->load('translations');

        $coverage = TranslationCoverage::for($this->page, ['title', 'meta_description']);

        $this->assertSame(1, $coverage['fr']['approved']);
        $this->assertSame(1, $coverage['fr']['machine']);
        $this->assertSame(0, $coverage['fr']['untranslated']);

        // Half the fields are drafted by a machine, so the language is not
        // ready even though every field has text in it.
        $this->assertFalse($coverage['fr']['ready']);
    }

    public function test_a_language_that_is_entirely_machine_drafted_does_not_read_as_finished(): void
    {
        $this->page->setTranslations('pt', ['title' => 'Titulo', 'meta_description' => 'Descricao']);
        Translation::where('locale', 'pt')->update(['is_machine_draft' => true]);

        $this->page->refresh()->load('translations');
        $coverage = TranslationCoverage::for($this->page, ['title', 'meta_description']);

        $this->assertFalse($coverage['pt']['ready']);
        $this->assertSame(0, TranslationCoverage::percentage($coverage['pt']));

        // And the sentence beside the bar says what is actually there,
        // which a single percentage could not.
        $this->assertStringContainsString(__('admin.coverage_machine', ['n' => 2]), TranslationCoverage::describe($coverage['pt']));
    }

    public function test_coverage_is_shown_as_a_sentence_not_only_a_bar(): void
    {
        $this->actingAs($this->editor)
            ->get('/en/admin/translations?type=pages')
            ->assertOk()
            ->assertSee(__('admin.coverage_untranslated', ['n' => 3]));
    }

    /* The editor -------------------------------------------------------------- */

    public function test_the_editor_shows_three_columns_side_by_side_and_two_in_a_pair_view(): void
    {
        $all = $this->actingAs($this->editor)
            ->get('/en/admin/translations/pages/'.$this->page->id)
            ->assertOk()
            ->getContent();

        foreach (Locales::SUPPORTED as $locale) {
            $this->assertStringContainsString('name="'.$locale.'[title]"', $all);
        }
        $this->assertStringContainsString('--a-cols:3', $all);

        $pair = $this->actingAs($this->editor)
            ->get('/en/admin/translations/pages/'.$this->page->id.'?pair=pt')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="en[title]"', $pair);
        $this->assertStringContainsString('name="pt[title]"', $pair);
        $this->assertStringNotContainsString('name="fr[title]"', $pair);
        $this->assertStringContainsString('--a-cols:2', $pair);
    }

    public function test_an_untranslated_field_says_what_a_reader_will_actually_see(): void
    {
        $this->actingAs($this->editor)
            ->get('/en/admin/translations/pages/'.$this->page->id)
            ->assertOk()
            ->assertSee(__('admin.no_translation', ['language' => Locales::nameOf('pt')]))
            ->assertSee(__('admin.fallback_explained', ['language' => Locales::nameOf('pt')]));
    }

    public function test_approving_a_machine_draft_clears_the_flag_without_changing_the_text(): void
    {
        $this->page->setTranslations('fr', ['title' => 'Lignes directrices']);
        Translation::where('locale', 'fr')->update(['is_machine_draft' => true]);

        $this->actingAs($this->editor)
            ->from('/en/admin/translations/pages/'.$this->page->id)
            ->put('/en/admin/translations/pages/'.$this->page->id.'/approve?field=title&approve_locale=fr')
            ->assertRedirect();

        $translation = Translation::where('locale', 'fr')->where('field', 'title')->sole();

        $this->assertFalse($translation->is_machine_draft);
        $this->assertSame('Lignes directrices', $translation->value);
        $this->assertSame($this->editor->id, $translation->translated_by);
    }

    public function test_rewriting_a_machine_draft_clears_the_flag_too(): void
    {
        $this->page->setTranslations('fr', ['title' => 'Machine output']);
        Translation::where('locale', 'fr')->update(['is_machine_draft' => true]);

        $this->actingAs($this->editor)
            ->put('/en/admin/translations/pages/'.$this->page->id, [
                'en' => ['title' => 'SADC roaming guidelines'],
                'fr' => ['title' => 'Lignes directrices de la SADC sur itinerance'],
            ]);

        $translation = Translation::where('locale', 'fr')->where('field', 'title')->sole();

        $this->assertFalse($translation->is_machine_draft);
        $this->assertSame('Lignes directrices de la SADC sur itinerance', $translation->value);
    }

    /* Preview ---------------------------------------------------------------- */

    public function test_the_preview_runs_the_real_resolver_and_shows_the_fallback_notice(): void
    {
        $html = $this->actingAs($this->editor)
            ->get('/en/admin/translations/pages/'.$this->page->id.'/preview/pt')
            ->assertOk()
            ->getContent();

        // The English text, marked as English so a screen reader switches
        // voice, under the notice a reader would actually get.
        $this->assertStringContainsString('SADC roaming guidelines', $html);
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString(__('site.fallback_title', ['language' => __('site.languages.pt', [], 'pt')], 'pt'), $html);
    }

    public function test_the_preview_of_a_translated_field_carries_no_fallback_notice(): void
    {
        $this->page->setTranslations('pt', ['title' => 'Diretrizes de roaming da SADC', 'meta_description' => 'O quadro regional.']);

        $html = $this->actingAs($this->editor)
            ->get('/en/admin/translations/pages/'.$this->page->id.'/preview/pt')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Diretrizes de roaming da SADC', $html);
        $this->assertStringNotContainsString(__('site.fallback_title', ['language' => __('site.languages.pt', [], 'pt')], 'pt'), $html);
    }

    /* Access ----------------------------------------------------------------- */

    public function test_a_viewer_reaches_no_part_of_the_translation_editor(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/translations')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/translations/pages/'.$this->page->id)->assertForbidden();
        $this->actingAs($viewer)
            ->put('/en/admin/translations/pages/'.$this->page->id, ['fr' => ['title' => 'x']])
            ->assertForbidden();
    }

    public function test_an_unknown_record_type_is_a_404_not_a_guess(): void
    {
        $this->actingAs($this->editor)
            ->get('/en/admin/translations/users/1')
            ->assertNotFound();
    }
}
