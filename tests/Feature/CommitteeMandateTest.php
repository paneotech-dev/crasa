<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A committee's terms of reference are per language.
 *
 * They were a plain column on a trilingual platform until 10 September 2026,
 * so a Francophone regulator reading the ARPTC's seat met the English text
 * with no notice and no way to translate it. CLAUDE.md's multilingual rules
 * put body prose in the translations table and are explicit that a
 * per-language field never becomes a mandate_fr column.
 */
class CommitteeMandateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $this->admin = User::factory()->administrator()->create();
    }

    private function committee(): Committee
    {
        return Committee::where('slug', 'spectrum-and-frequency-management')->sole();
    }

    public function test_the_column_is_gone_and_the_text_is_in_the_translations_table(): void
    {
        $this->assertNotContains('mandate', Schema::getColumnListing('committees'));
        $this->assertNotContains('mandate_fr', Schema::getColumnListing('committees'));

        // The seeded English survived the move.
        $this->assertStringContainsString(
            'spectrum allocation',
            $this->committee()->translated('mandate', '')
        );

        $this->assertDatabaseHas('translations', [
            'translatable_type' => Committee::class,
            'translatable_id' => $this->committee()->id,
            'locale' => 'en',
            'field' => 'mandate',
        ]);
    }

    public function test_each_language_is_served_its_own_mandate(): void
    {
        $committee = $this->committee();

        $committee->setTranslations('fr', ['mandate' => "Coordonne l'assignation du spectre entre les frontières."]);
        $committee->setTranslations('pt', ['mandate' => 'Coordena a atribuição de espetro entre fronteiras.']);

        $this->get('/en/committees')->assertOk()->assertSee('spectrum allocation', false);
        // Without the apostrophe: Blade escapes it to &#039;, and this is
        // a test about which language is served rather than about escaping.
        $this->get('/fr/committees')->assertOk()->assertSee('assignation du spectre', false);
        $this->get('/pt/committees')->assertOk()->assertSee('atribuição de espetro', false);
    }

    /**
     * A missing translation renders the English with a notice rather than a
     * blank. CLAUDE.md: never a blank page, never a hidden record.
     */
    public function test_a_missing_translation_falls_back_to_english(): void
    {
        $html = $this->get('/pt/committees')->assertOk()->getContent();

        $this->assertStringContainsString('spectrum allocation', $html);
    }

    /** The translation editor lists committees now. */
    public function test_the_translation_editor_opens_a_committee(): void
    {
        $committee = $this->committee();

        $this->actingAs($this->admin)->get('/en/admin/translations')->assertOk();

        $this->actingAs($this->admin)
            ->get("/en/admin/translations/committees/{$committee->id}")
            ->assertOk()
            ->assertSee('spectrum allocation', false);

        $this->actingAs($this->admin)
            ->get("/en/admin/translations/committees/{$committee->id}/preview/fr")
            ->assertOk();
    }

    /**
     * A save that changes only a translation used to write nothing to the
     * audit log, because Auditable watches the model's own attributes and
     * nothing was dirty. "Who changed this committee's mandate" now has an
     * answer.
     */
    public function test_a_translation_only_change_is_audited(): void
    {
        $committee = $this->committee();

        $this->actingAs($this->admin)->put("/en/admin/data/committees/{$committee->id}", [
            'name' => $committee->name,
            'sector_id' => $committee->sector_id,
            'en' => ['mandate' => 'Rewritten in English only.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = AuditLog::where('auditable_type', Committee::class)
            ->where('auditable_id', $committee->id)
            ->where('action', AuditLog::UPDATED)
            ->latest('id')
            ->firstOrFail();

        // Day 3 morning (1g): the field and language, with before and after.
        $this->assertSame(['mandate.en'], array_keys($entry->changes));
        $this->assertSame('Rewritten in English only.', $entry->changes['mandate.en']['to']);
        $this->assertArrayHasKey('from', $entry->changes['mandate.en']);

        // And a save that changes nothing writes nothing.
        $before = AuditLog::where('auditable_type', Committee::class)->count();

        $this->actingAs($this->admin)->put("/en/admin/data/committees/{$committee->id}", [
            'name' => $committee->name,
            'sector_id' => $committee->sector_id,
            'en' => ['mandate' => 'Rewritten in English only.'],
        ])->assertRedirect();

        $this->assertSame($before, AuditLog::where('auditable_type', Committee::class)->count());
    }
}
