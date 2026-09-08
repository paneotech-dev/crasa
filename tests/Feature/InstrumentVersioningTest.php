<?php

namespace Tests\Feature;

use App\Enums\InstrumentStatus;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\User;
use App\Services\InstrumentVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InstrumentVersioningTest extends TestCase
{
    use RefreshDatabase;

    private InstrumentVersioning $versioning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->versioning = app(InstrumentVersioning::class);
    }

    /**
     * The requirement in full: ten edits give ten versions and the original
     * text is still retrievable.
     */
    public function test_ten_edits_produce_ten_versions_and_the_original_text_survives(): void
    {
        $instrument = Instrument::factory()->create();

        $this->versioning->record($instrument, 'First issue', [
            'en' => ['title' => 'Mobile termination rate determination', 'body' => 'Original text.'],
        ]);

        for ($edit = 2; $edit <= 10; $edit++) {
            $this->versioning->record($instrument, "Edit {$edit}", [
                'en' => ['title' => "Revision {$edit}", 'body' => "Body as at revision {$edit}."],
            ]);
        }

        $versions = $instrument->fresh()->versions()->reorder('version_number')->get();

        $this->assertCount(10, $versions);
        $this->assertSame(range(1, 10), $versions->pluck('version_number')->map(fn ($n) => (int) $n)->all());

        // The original, nine edits later.
        $this->assertSame('Mobile termination rate determination', $versions->first()->valueFor('title', 'en'));
        $this->assertSame('Original text.', $versions->first()->valueFor('body', 'en'));

        // And the current text is the tenth.
        $this->assertSame('Revision 10', $instrument->fresh()->translate('title', 'en')->value);
    }

    public function test_the_instrument_points_at_its_latest_version(): void
    {
        $instrument = Instrument::factory()->create();

        $this->versioning->record($instrument, 'First', ['en' => ['title' => 'One']]);
        $second = $this->versioning->record($instrument, 'Second', ['en' => ['title' => 'Two']]);

        $this->assertSame($second->id, $instrument->fresh()->current_version_id);
        $this->assertSame(2, (int) $instrument->fresh()->currentVersion->version_number);
    }

    public function test_a_version_snapshots_every_language_not_only_the_edited_one(): void
    {
        $instrument = Instrument::factory()->create();

        $version = $this->versioning->record($instrument, 'Trilingual', [
            'en' => ['title' => 'Number portability regulations'],
            'fr' => ['title' => 'Règlement sur la portabilité des numéros'],
            'pt' => ['title' => 'Regulamento sobre a portabilidade de números'],
        ]);

        $this->assertSame('Number portability regulations', $version->valueFor('title', 'en'));
        $this->assertSame('Règlement sur la portabilité des numéros', $version->valueFor('title', 'fr'));
        $this->assertSame('Regulamento sobre a portabilidade de números', $version->valueFor('title', 'pt'));
    }

    public function test_a_version_records_who_changed_it_and_when(): void
    {
        $editor = User::factory()->editor()->create();
        $instrument = Instrument::factory()->create();

        $version = $this->versioning->record($instrument, 'Edited', ['en' => ['title' => 'X']], $editor->id);

        $this->assertSame($editor->id, $version->changed_by);
        $this->assertNotNull($version->created_at);
        $this->assertSame('Edited', $version->change_summary);
    }

    public function test_version_history_cannot_be_rewritten(): void
    {
        $instrument = Instrument::factory()->create();
        $version = $this->versioning->record($instrument, 'First', ['en' => ['title' => 'One']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be rewritten');

        $version->update(['change_summary' => 'Something else']);
    }

    public function test_version_history_cannot_be_deleted(): void
    {
        $instrument = Instrument::factory()->create();
        $version = $this->versioning->record($instrument, 'First', ['en' => ['title' => 'One']]);

        $this->expectException(RuntimeException::class);

        $version->delete();
    }

    public function test_two_instruments_number_their_versions_independently(): void
    {
        $one = Instrument::factory()->create();
        $two = Instrument::factory()->create();

        $this->versioning->record($one, 'a', ['en' => ['title' => 'A']]);
        $this->versioning->record($one, 'b', ['en' => ['title' => 'B']]);
        $first = $this->versioning->record($two, 'c', ['en' => ['title' => 'C']]);

        $this->assertSame(1, (int) $first->version_number);
        $this->assertSame(2, (int) InstrumentVersion::where('instrument_id', $one->id)->max('version_number'));
    }

    /* Slug permanence ------------------------------------------------------ */

    public function test_a_published_instruments_slug_is_permanent(): void
    {
        $instrument = Instrument::factory()->inForce()->create(['slug' => 'na-roaming-guidelines']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('citation permalink');

        $instrument->update(['slug' => 'na-roaming-guidelines-v2']);
    }

    public function test_a_draft_slug_may_still_be_corrected(): void
    {
        $instrument = Instrument::factory()->draft()->create(['slug' => 'typo-here']);

        $instrument->update(['slug' => 'corrected']);

        $this->assertSame('corrected', $instrument->fresh()->slug);
    }

    public function test_a_repealed_instrument_keeps_its_slug_because_it_is_still_cited(): void
    {
        $instrument = Instrument::factory()->repealed()->create(['slug' => 'zm-postal-act']);

        $this->expectException(RuntimeException::class);

        $instrument->update(['slug' => 'zm-postal-act-old']);
    }

    public function test_other_fields_on_a_published_instrument_are_still_editable(): void
    {
        $instrument = Instrument::factory()->inForce()->create();

        $instrument->update(['status' => InstrumentStatus::Amended]);

        $this->assertSame(InstrumentStatus::Amended, $instrument->fresh()->status);
    }

    /* Supersession --------------------------------------------------------- */

    public function test_supersession_is_navigable_in_both_directions(): void
    {
        $old = Instrument::factory()->repealed()->create(['slug' => 'old']);
        $new = Instrument::factory()->inForce()->create(['slug' => 'new']);

        $old->update(['superseded_by_id' => $new->id]);

        $this->assertTrue($old->fresh()->isSuperseded());
        $this->assertSame($new->id, $old->fresh()->supersededBy->id);
        $this->assertTrue($new->fresh()->supersedes->contains($old));
    }
}
