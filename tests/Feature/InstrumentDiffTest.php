<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\User;
use App\Services\InstrumentVersioning;
use App\Support\TextDiff;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What changed between two versions of a regulatory instrument.
 *
 * RP-07 has written a version on every edit since the schema was created
 * and nothing read it: an editor could see that an instrument had nine
 * versions and not what any of them said, which makes a history a count.
 *
 * WORD BY WORD, NOT LINE BY LINE. A line diff on regulatory prose marks a
 * whole paragraph changed because one date moved in it, and "what exactly
 * changed in clause 4" is the question a regulator asks.
 */
class InstrumentDiffTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Instrument $instrument;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->administrator = User::factory()->administrator()->create();

        $this->instrument = Instrument::factory()->create(['slug' => 'quality-of-service-2019']);
    }

    private function version(string $body, string $summary): void
    {
        app(InstrumentVersioning::class)->record(
            $this->instrument,
            $summary,
            ['en' => ['title' => 'Quality of service regulations', 'body' => $body]],
            $this->administrator->id,
        );
    }

    /* The algorithm ------------------------------------------------------- */

    public function test_the_diff_marks_the_words_that_changed_and_nothing_else(): void
    {
        $diff = TextDiff::between(
            'The operator shall report within 30 days of the end of each quarter.',
            'The operator shall report within 45 days of the end of each calendar quarter.',
        );

        $kept = collect($diff['segments'])->where('type', 'kept')->pluck('text')->implode('');

        // The sentence around the change survives whole.
        $this->assertStringContainsString('The operator shall report within', $kept);
        $this->assertStringContainsString('days of the end of each', $kept);

        $this->assertSame(
            ['45', 'calendar'],
            collect($diff['segments'])->where('type', 'added')->map(fn ($s) => trim($s['text']))->values()->all()
        );
        $this->assertSame(
            ['30'],
            collect($diff['segments'])->where('type', 'removed')->map(fn ($s) => trim($s['text']))->values()->all()
        );

        $this->assertSame(2, $diff['added']);
        $this->assertSame(1, $diff['removed']);
    }

    /** Joining the segments reproduces both texts exactly. */
    public function test_the_diff_loses_nothing(): void
    {
        $before = "Clause 4.\n\nThe licensee shall publish its tariffs.";
        $after = "Clause 4.\n\nThe licensee shall publish and maintain its tariffs annually.";

        $diff = TextDiff::between($before, $after);

        $left = collect($diff['segments'])->whereIn('type', ['kept', 'removed'])->pluck('text')->implode('');
        $right = collect($diff['segments'])->whereIn('type', ['kept', 'added'])->pluck('text')->implode('');

        $this->assertSame($before, $left);
        $this->assertSame($after, $right);
    }

    public function test_identical_text_produces_no_marks(): void
    {
        $diff = TextDiff::between('The same words.', 'The same words.');

        $this->assertSame(0, $diff['added']);
        $this->assertSame(0, $diff['removed']);
        $this->assertSame(['kept'], collect($diff['segments'])->pluck('type')->unique()->values()->all());
    }

    /**
     * A pair too long to compare says so rather than taking a gigabyte to
     * draw. The table is O(n*m).
     */
    public function test_a_very_long_field_is_refused_rather_than_attempted(): void
    {
        $long = str_repeat('word ', TextDiff::MAX_WORDS + 10);

        $diff = TextDiff::between($long, $long.' more');

        $this->assertTrue($diff['truncated']);
        $this->assertSame([], $diff['segments']);
    }

    /* The screen ----------------------------------------------------------- */

    public function test_the_history_lists_every_version_with_its_author(): void
    {
        $this->version('First text.', 'Created');
        $this->version('Second text.', 'Corrected a reference');

        $this->actingAs($this->administrator)
            ->get('/en/admin/instruments/'.$this->instrument->slug.'/versions')
            ->assertOk()
            ->assertSee('Corrected a reference')
            ->assertSee($this->administrator->name)
            ->assertSee(__('admin.page_history_version', ['n' => 2]));
    }

    public function test_the_diff_defaults_to_the_newest_against_the_one_before(): void
    {
        $this->version('The operator shall report within 30 days.', 'Created');
        $this->version('The operator shall report within 45 days.', 'Period extended');

        $html = $this->actingAs($this->administrator)
            ->get('/en/admin/instruments/'.$this->instrument->slug.'/diff')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('a-diff__added', $html);
        $this->assertStringContainsString('a-diff__removed', $html);
        $this->assertStringContainsString('45', $html);
        $this->assertStringContainsString('Period extended', $html);
    }

    /** A comparison is a URL somebody can send. */
    public function test_any_two_versions_can_be_chosen(): void
    {
        $this->version('One.', 'First');
        $this->version('Two.', 'Second');
        $this->version('Three.', 'Third');

        $versions = $this->instrument->versions()->reorder('version_number')->get();

        $this->actingAs($this->administrator)->get(
            '/en/admin/instruments/'.$this->instrument->slug.'/diff'
            .'?from='.$versions[0]->id.'&to='.$versions[2]->id
        )->assertOk()->assertSee('Third');
    }

    /** Each language is diffed on its own. */
    public function test_the_diff_can_be_read_in_any_language(): void
    {
        app(InstrumentVersioning::class)->record($this->instrument, 'Created', [
            'en' => ['title' => 'Roaming', 'body' => 'Thirty days.'],
            'fr' => ['title' => 'Itinérance', 'body' => 'Trente jours.'],
        ], $this->administrator->id);

        app(InstrumentVersioning::class)->record($this->instrument, 'Extended', [
            'en' => ['title' => 'Roaming', 'body' => 'Forty-five days.'],
            'fr' => ['title' => 'Itinérance', 'body' => 'Quarante-cinq jours.'],
        ], $this->administrator->id);

        $this->actingAs($this->administrator)
            ->get('/en/admin/instruments/'.$this->instrument->slug.'/diff?lang=fr')
            ->assertOk()
            ->assertSee('Quarante-cinq', false)
            ->assertSee('Trente', false);
    }

    /** One version is nothing to compare, and it says so. */
    public function test_one_version_is_not_a_comparison(): void
    {
        $this->version('Only text.', 'Created');

        $this->actingAs($this->administrator)
            ->get('/en/admin/instruments/'.$this->instrument->slug.'/diff')
            ->assertNotFound();
    }

    /* Not colour alone ----------------------------------------------------- */

    /**
     * An addition carries a + and an underline, a removal a minus and a
     * strike, and each is named to a screen reader. Colour alone fails
     * SC 1.4.1.
     */
    public function test_a_change_is_marked_in_more_than_colour(): void
    {
        $this->version('Thirty days.', 'Created');
        $this->version('Forty days.', 'Extended');

        $html = $this->actingAs($this->administrator)
            ->get('/en/admin/instruments/'.$this->instrument->slug.'/diff')
            ->assertOk()
            ->getContent();

        // Semantic elements, not styled spans.
        $this->assertStringContainsString('<ins class="a-diff__added"', $html);
        $this->assertStringContainsString('<del class="a-diff__removed"', $html);

        // Named to a screen reader.
        $this->assertStringContainsString(__('admin.diff_added_from'), $html);
        $this->assertStringContainsString(__('admin.diff_removed_from'), $html);

        $css = file_get_contents(public_path('css/admin.css'));

        // Anchored to a line start, so the shared rule that resets both is
        // not what gets matched.
        preg_match('/^\.a-diff__added \{([^}]*)\}/m', $css, $added);
        preg_match('/^\.a-diff__removed \{([^}]*)\}/m', $css, $removed);

        $this->assertStringContainsString('text-decoration: underline', $added[1]);
        $this->assertStringContainsString('text-decoration: line-through', $removed[1]);

        // And the marks survive Windows high contrast, which drops fills.
        $this->assertMatchesRegularExpression(
            '/@media \(forced-colors: active\) \{[^}]*\.a-diff__added,\s*\.a-diff__removed \{ background: none/s',
            $css
        );
    }

    /* Reachability and permission ------------------------------------------ */

    public function test_the_instrument_editor_links_to_its_history(): void
    {
        $this->version('Text.', 'Created');

        $this->actingAs($this->administrator)
            ->get('/en/admin/data/instruments/'.$this->instrument->id)
            ->assertOk()
            ->assertSee('/en/admin/instruments/'.$this->instrument->slug.'/versions', false);
    }

    public function test_a_viewer_cannot_read_the_history(): void
    {
        $this->version('Text.', 'Created');
        $this->version('More text.', 'Edited');

        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/instruments/'.$this->instrument->slug.'/versions')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/instruments/'.$this->instrument->slug.'/diff')->assertForbidden();
    }
}
