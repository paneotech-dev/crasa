<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Supersession, from the admin form to the rendered lineage.
 *
 * The model has carried supersedes_id since the schema was written and
 * screen 07 draws the lineage on the document page. There was no way to set
 * it: an editor could publish a 2024 edition of a guideline and had no means
 * of saying what it replaced, so every reader of the 2019 edition was told
 * it was current.
 *
 * The picker offers only valid candidates and the server enforces the same
 * four rules whatever the form sends, because a select is a convenience and
 * not access control.
 */
class SupersessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->admin = User::factory()->administrator()->create();
        $this->sector = Sector::where('slug', 'telecommunications')->sole();
    }

    private function publication(string $title, string $date, string $type = 'guideline'): Publication
    {
        $publication = Publication::create([
            'publication_type' => $type,
            'sector_id' => $this->sector->id,
            'published_on' => $date,
            'is_public' => true,
            'slug' => \Illuminate\Support\Str::slug($title),
        ]);

        $publication->setTranslations('en', ['title' => $title, 'summary' => 'A guideline.']);

        // A published publication has its file (INC-04): the form refuses
        // to keep one published without it.
        \App\Models\Document::factory()->create([
            'documentable_type' => $publication->getMorphClass(),
            'documentable_id' => $publication->id,
        ]);

        return $publication->fresh();
    }

    /** @param array<string, mixed> $extra */
    private function save(Publication $publication, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(
            "/en/admin/data/publications/{$publication->id}",
            array_merge([
                // Read as a term on the model; the form sends its slug.
                'publication_type' => $publication->typeSlug(),
                'sector_id' => $publication->sector_id,
                'published_on' => $publication->published_on->toDateString(),
                'is_public' => 1,
                'en' => ['title' => $publication->translated('title', ''), 'summary' => 'A guideline.'],
            ], $extra)
        );
    }

    /* The chain, end to end -------------------------------------------- */

    public function test_the_form_sets_supersession_and_both_public_pages_show_the_lineage(): void
    {
        $first = $this->publication('SADC roaming guidelines, first edition', '2019-06-01');
        $second = $this->publication('SADC roaming guidelines, second edition', '2021-06-01');
        $third = $this->publication('SADC roaming guidelines, third edition', '2024-06-01');

        // The picker offers the earlier ones and not the later one.
        $form = $this->actingAs($this->admin)
            ->get("/en/admin/data/publications/{$second->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('first edition', $form);
        $this->assertStringNotContainsString('third edition', $form);
        $this->assertStringContainsString(__('admin.field_supersedes_id'), $form);

        // Set it, twice, to build a chain of three.
        $this->save($second, ['supersedes_id' => $first->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->save($third, ['supersedes_id' => $second->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($first->id, $second->fresh()->supersedes_id);
        $this->assertSame($second->id, $third->fresh()->supersedes_id);

        // The lineage runs the whole chain from any point in it.
        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $second->fresh()->lineage()->pluck('id')->all()
        );

        // And it is on the public page of the oldest and of the newest.
        $oldest = $this->get('/en/publications/'.$first->slug)->assertOk()->getContent();
        $this->assertStringContainsString(__('publications.lineage'), $oldest);
        $this->assertStringContainsString('third edition', $oldest);
        $this->assertStringContainsString(__('publications.superseded'), $oldest);

        $newest = $this->get('/en/publications/'.$third->slug)->assertOk()->getContent();
        $this->assertStringContainsString('first edition', $newest);
        $this->assertStringContainsString(__('publications.you_are_here'), $newest);
    }

    /** The reverse is shown on the earlier document's form, and not typed. */
    public function test_the_reverse_is_shown_read_only(): void
    {
        $first = $this->publication('Numbering plan, 2019', '2019-01-01');
        $second = $this->publication('Numbering plan, 2024', '2024-01-01');

        $this->save($second, ['supersedes_id' => $first->id])->assertRedirect();

        $form = $this->actingAs($this->admin)
            ->get("/en/admin/data/publications/{$first->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('admin.field_superseded_by'), $form);
        $this->assertStringContainsString('Numbering plan, 2024', $form);

        // Shown, not a control: there is no input or select named for it.
        $this->assertStringNotContainsString('name="superseded_by"', $form);

        // And a request that sends one anyway changes nothing.
        $this->save($first, ['superseded_by' => 'anything'])->assertRedirect();
        $this->assertSame($second->id, $first->fresh()->supersededBy()->first()?->id);
    }

    /* The four rules, on the server ------------------------------------ */

    public function test_a_document_cannot_supersede_itself(): void
    {
        $one = $this->publication('Spectrum framework', '2022-01-01');

        $this->save($one, ['supersedes_id' => $one->id])
            ->assertSessionHasErrors('supersedes_id');

        $this->assertNull($one->fresh()->supersedes_id);
    }

    public function test_a_document_cannot_supersede_another_type(): void
    {
        $report = $this->publication('Annual report 2019', '2019-01-01', 'annual_report');
        $guideline = $this->publication('Interconnection guidelines 2024', '2024-01-01');

        $this->save($guideline, ['supersedes_id' => $report->id])
            ->assertSessionHasErrors('supersedes_id');
    }

    public function test_a_document_cannot_supersede_a_later_one(): void
    {
        $later = $this->publication('Quality of service guidelines, 2024', '2024-01-01');
        $earlier = $this->publication('Quality of service guidelines, 2019', '2019-01-01');

        // The earlier document trying to supersede the later one.
        $this->save($earlier, ['supersedes_id' => $later->id])
            ->assertSessionHasErrors('supersedes_id');
    }

    /**
     * The one that hangs the renderer rather than merely being wrong.
     * lineage() walks both directions with a guard of twenty; a loop would
     * still produce a chain nobody can read.
     */
    public function test_a_chain_cannot_be_closed_into_a_loop(): void
    {
        $first = $this->publication('Postal guidelines, 2016', '2016-01-01');
        $second = $this->publication('Postal guidelines, 2020', '2020-01-01');

        $this->save($second, ['supersedes_id' => $first->id])->assertRedirect();

        /*
         * Closing the loop needs the date rule out of the way, so the
         * request claims a date later than the second edition's. The loop
         * check is the one that has to catch it, and it does.
         */
        $this->actingAs($this->admin)->put("/en/admin/data/publications/{$first->id}", [
            'publication_type' => 'guideline',
            'sector_id' => $this->sector->id,
            'published_on' => '2024-01-01',
            'is_public' => 1,
            'supersedes_id' => $second->id,
            'en' => ['title' => 'Postal guidelines, 2016', 'summary' => 'A guideline.'],
        ])->assertSessionHasErrors('supersedes_id');

        $this->assertNull($first->fresh()->supersedes_id);
    }

    /* The record of it -------------------------------------------------- */

    public function test_setting_supersession_writes_an_audit_row(): void
    {
        $first = $this->publication('Licensing framework, 2018', '2018-01-01');
        $second = $this->publication('Licensing framework, 2023', '2023-01-01');

        $this->save($second, ['supersedes_id' => $first->id])->assertRedirect();

        $entry = AuditLog::where('auditable_type', Publication::class)
            ->where('auditable_id', $second->id)
            ->where('action', AuditLog::UPDATED)
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('supersedes_id', $entry->changes);
    }

    /** Clearing it is a change too, and the public page follows. */
    public function test_supersession_can_be_cleared(): void
    {
        $first = $this->publication('Consumer guidelines, 2018', '2018-01-01');
        $second = $this->publication('Consumer guidelines, 2023', '2023-01-01');

        $this->save($second, ['supersedes_id' => $first->id])->assertRedirect();
        $this->assertTrue($first->fresh()->isSuperseded());

        $this->save($second, ['supersedes_id' => ''])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($second->fresh()->supersedes_id);
        $this->assertFalse($first->fresh()->isSuperseded());

        $this->get('/en/publications/'.$first->slug)
            ->assertOk()
            ->assertDontSee(__('publications.superseded_title'));
    }
}
