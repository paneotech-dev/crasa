<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Project;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Projects, WS-12's no-content-loss clause.
 *
 * The legacy site carries two project sections behind a top-level menu item
 * and WS-03's information architecture lists neither. Current and past are
 * one collection in two states here, so a project keeps its URL on the day
 * it finishes.
 */
class ProjectsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    private function project(string $slug, string $state, string $title): Project
    {
        $project = Project::create([
            'slug' => $slug,
            'state' => $state,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'is_sample' => false,
        ]);

        $project->setTranslation('title', 'en', $title);

        return $project;
    }

    public function test_the_listing_shows_current_and_past_together(): void
    {
        $this->project('roadmap', 'current', 'IMT 2020 deployment roadmap');
        $this->project('reform', 'past', 'SADC postal sector reform');

        $this->get('/en/projects')
            ->assertOk()
            ->assertSee('IMT 2020 deployment roadmap')
            ->assertSee('SADC postal sector reform');
    }

    public function test_the_state_filter_narrows_the_listing(): void
    {
        $this->project('roadmap', 'current', 'IMT 2020 deployment roadmap');
        $this->project('reform', 'past', 'SADC postal sector reform');

        $this->get('/en/projects?state=past')
            ->assertOk()
            ->assertSee('SADC postal sector reform')
            ->assertDontSee('IMT 2020 deployment roadmap');
    }

    /**
     * The whole reason current and past are one collection. A project that
     * finishes must not change URL, or every citation of it breaks on the
     * day it succeeds.
     */
    public function test_a_project_keeps_its_url_when_it_moves_from_current_to_past(): void
    {
        $project = $this->project('reform', 'current', 'SADC postal sector reform');

        $this->get('/en/projects/reform')->assertOk();

        $project->update(['state' => 'past']);

        $this->get('/en/projects/reform')->assertOk()->assertSee('SADC postal sector reform');
    }

    public function test_an_unpublished_project_is_not_reachable(): void
    {
        $this->project('draft-one', 'current', 'Not published')->update(['status' => 'draft']);

        $this->get('/en/projects/draft-one')->assertNotFound();
    }

    public function test_the_listing_shows_its_empty_state_rather_than_a_blank_page(): void
    {
        $this->get('/en/projects')
            ->assertOk()
            ->assertSee(__('projects.none_title'));
    }

    /**
     * The menu group was seeded hidden with its children as records, so
     * turning it on was a toggle rather than a deploy. This is the toggle.
     */
    public function test_the_projects_menu_group_is_visible_with_both_children(): void
    {
        $this->seed(HeaderMenuSeeder::class);

        $projects = MenuItem::where('menu', 'header')
            ->whereNull('parent_id')
            ->get()
            ->first(fn (MenuItem $item) => $item->translated('label') === 'Projects');

        $this->assertNotNull($projects, 'The header menu has no Projects group.');
        $this->assertTrue($projects->is_visible);
        $this->assertSame(2, $projects->children()->count());
    }

    public function test_the_listing_is_in_all_three_locales(): void
    {
        $this->project('roadmap', 'current', 'IMT 2020 deployment roadmap');

        foreach (['en' => 'Projects', 'fr' => 'Projets', 'pt' => 'Projetos'] as $locale => $heading) {
            $this->get("/{$locale}/projects")->assertOk()->assertSee($heading);
        }
    }

    /**
     * S8 (29 October 2026): a hero with the current and completed counts,
     * cards with a status pill in words and the partners as chips, and a
     * project page with a facts panel. A field the record does not hold is
     * left out rather than shown as a dash.
     */
    public function test_cards_and_the_project_page_show_what_the_record_holds(): void
    {
        $roadmap = $this->project('roadmap', 'current', 'IMT 2020 deployment roadmap');
        $roadmap->update(['partner' => 'ITU, GSMA']);
        $this->project('reform', 'past', 'SADC postal sector reform');

        $html = $this->get('/en/projects')->assertOk()->getContent();
        $this->assertStringContainsString('class="u-figures"', $html);
        $this->assertStringContainsString(__('cpages_a.figure_completed'), $html);
        $this->assertStringContainsString('<li>ITU</li>', $html);
        $this->assertStringContainsString(__('cpages_a.status_current'), $html);
        $this->assertStringContainsString(__('cpages_a.status_completed'), $html);
        $this->assertStringContainsString('u-cta', $html);

        $page = $this->get('/en/projects/reform')->assertOk()->getContent();
        $this->assertStringContainsString(__('cpages_a.facts'), $page);
        $this->assertStringNotContainsString('<dt>'.__('cpages_a.partners').'</dt>', $page);
        $this->assertStringNotContainsString('<dt>'.__('cpages_a.period').'</dt>', $page);
        $this->assertStringNotContainsString('<dd>-</dd>', $page);
    }
}
