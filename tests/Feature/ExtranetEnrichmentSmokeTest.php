<?php

namespace Tests\Feature;

use App\Enums\ContributionState;
use App\Models\Announcement;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every new extranet surface answers, in every locale.
 *
 * A smoke test rather than a behaviour test: the behaviour is pinned in the
 * dedicated tests beside this one. What this catches is the failure mode
 * that costs a morning at a workshop, which is a page that 500s because a
 * lang key or a route parameter is wrong in one of the three languages.
 */
class ExtranetEnrichmentSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->rep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);
    }

    public function test_every_new_member_surface_answers_in_all_three_locales(): void
    {
        $announcement = Announcement::create([
            'slug' => 'a-notice',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $announcement->setTranslation('title', 'en', 'A notice to the membership');
        $announcement->setTranslation('body', 'en', 'The body of the notice.');

        $instrument = $this->contribution();

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach ([
                "/{$locale}/extranet/dashboard",
                "/{$locale}/announcements",
                "/{$locale}/announcements/a-notice",
                "/{$locale}/notifications",
                "/{$locale}/contributions",
                "/{$locale}/contributions/new",
                "/{$locale}/contributions/{$instrument->slug}",
                "/{$locale}/register",
                "/{$locale}/authority",
                "/{$locale}/directory",
                "/{$locale}/indicators",
            ] as $path) {
                $this->actingAs($this->rep)->get($path)->assertOk();
            }
        }
    }

    public function test_the_register_shows_a_validated_instrument_and_hides_a_draft(): void
    {
        $draft = $this->contribution();

        $this->actingAs($this->rep)->get('/en/register')
            ->assertOk()
            ->assertDontSee('Botswana communications act');

        $draft->forceFill(['contribution_state' => ContributionState::Validated->value])->save();

        $this->actingAs($this->rep)->get('/en/register')
            ->assertOk()
            ->assertSee('Botswana communications act');

        $this->actingAs($this->rep)->get('/en/register/'.$draft->slug)->assertOk();
    }

    public function test_a_draft_contribution_is_not_reachable_in_the_register(): void
    {
        $draft = $this->contribution();

        $this->actingAs($this->rep)->get('/en/register/'.$draft->slug)->assertNotFound();
    }

    private function contribution(): Instrument
    {
        $instrument = Instrument::create([
            'country_id' => $this->rep->country_id,
            'sector_id' => Sector::first()->id,
            'instrument_type' => 'act',
            'reference_number' => 'BW Act 3 of 2024',
            'status' => 'in_force',
            'slug' => 'botswana-communications-act',
            'contribution_state' => ContributionState::Draft->value,
            'contributed_by' => $this->rep->id,
            'is_sample' => false,
        ]);

        $instrument->setTranslation('title', 'en', 'Botswana communications act');

        return $instrument;
    }
}
