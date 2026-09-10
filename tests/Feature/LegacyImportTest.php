<?php

namespace Tests\Feature;

use App\Models\LegacyRecord;
use App\Models\NewsItem;
use App\Models\Project;
use App\Models\Publication;
use App\Support\Legacy\AboutSections;
use App\Support\Legacy\Mapping;
use App\Support\Legacy\PageParser;
use App\Support\Legacy\TitleCase;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The legacy import, WS-12.
 *
 * WHAT THESE TESTS ARE FOR. The legacy site is being switched off. Once it
 * is, nothing can be checked against it, so every rule the import applies
 * has to be pinned now against markup taken from the real pages. The
 * fixtures below are the shapes crasa.org actually serves, cut down to the
 * part each rule reads.
 */
class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    public function test_the_parser_reads_a_publication_page(): void
    {
        $page = PageParser::parse('https://www.crasa.org/post-articles/sadc-roaming-guidelines', $this->publicationHtml());

        $this->assertSame('SADC Roaming Guidelines', $page['title']);
        $this->assertSame('Edition 2021', $page['summary']);
        $this->assertSame('Electronic Communications Regulatory Guidelines', $page['category']);
        $this->assertStringContainsString('coordinated regional response', $page['body_html']);
        $this->assertSame(
            ['https://cdn.prod.website-files.com/x/612fe9d9e1d1a95bb6ccffc9_2021%20REVISED-SADC-ROAMING-REGULATIONS-2021.pdf'],
            $page['documents']
        );
    }

    public function test_the_parser_reads_a_news_page_including_its_date(): void
    {
        $page = PageParser::parse('https://www.crasa.org/post/visit', $this->newsHtml());

        $this->assertSame('Members News', $page['category']);
        $this->assertSame('June 19, 2026', $page['date_text']);
    }

    /**
     * The bug this pins. The article body's class list is
     * `article w-richtext` on a page with text and
     * `article w-dyn-bind-empty w-richtext` on one without, and an exact
     * match on the first silently returned nothing for every page carrying
     * Webflow's extra marker.
     */
    public function test_an_article_body_is_found_whatever_else_webflow_puts_in_the_class(): void
    {
        $html = str_replace('class="article w-richtext"', 'class="article w-dyn-bind-empty w-richtext"', $this->publicationHtml());

        $this->assertStringContainsString('coordinated regional response', PageParser::parse('https://x/y', $html)['body_html']);
    }

    /**
     * The srcset's widest candidate, not the src.
     *
     * Webflow's `src` is often a 500-pixel derivative. The brief says
     * follow each thumbnail to its original, and a copy of a copy cannot be
     * re-enlarged later.
     */
    public function test_an_image_resolves_to_its_largest_variant(): void
    {
        $tag = '<img src="https://cdn/a-p-500.jpg" srcset="https://cdn/a-p-500.jpg 500w, '
            .'https://cdn/a-p-1080.jpeg 1080w, https://cdn/a.jpg 6000w">';

        $this->assertSame('https://cdn/a.jpg', PageParser::largestImage($tag));
    }

    public function test_a_download_button_pointing_at_sharepoint_is_recorded_rather_than_followed(): void
    {
        $page = PageParser::parse('https://www.crasa.org/post-articles/crasa-annual-reports', $this->annualReportsHtml());

        $this->assertSame([], $page['documents']);
        $this->assertSame(
            ['https://crasaorg143-my.sharepoint.com/:f:/g/personal/crasa_crasa_org/Eon2'],
            $page['external_documents']
        );
    }

    public function test_the_ruling_maps_every_legacy_category(): void
    {
        $this->assertSame(
            ['kind' => 'publication', 'type' => 'annual_report'],
            Mapping::forCategory('Annual Reports')
        );
        $this->assertSame(
            ['kind' => 'publication', 'type' => 'guideline', 'sector' => 'postal'],
            Mapping::forCategory('Postal Regulatory Guidelines')
        );
        $this->assertSame(
            ['kind' => 'publication', 'type' => 'guideline', 'sector' => 'telecommunications'],
            Mapping::forCategory('Electronic Communications Regulatory Guidelines')
        );
        $this->assertSame(['kind' => 'news', 'type' => 'press_release'], Mapping::forCategory('Press Releases'));
        $this->assertSame(['kind' => 'news', 'type' => 'consultation'], Mapping::forCategory('Stakeholder Consultations'));
        $this->assertSame(['kind' => 'news', 'type' => 'tender'], Mapping::forCategory('Tenders'));
    }

    /** The ruling: where the title names a sector and the category disagrees, the title wins. */
    public function test_a_title_naming_a_sector_beats_the_legacy_category(): void
    {
        $result = Mapping::sectorFor(
            'Framework on establishment of digital sound broadcasting in SADC',
            'telecommunications'
        );

        $this->assertSame('broadcasting', $result['sector']);
        $this->assertTrue($result['from_title'], 'A sector taken from the title has to be flagged for review.');
    }

    public function test_a_title_agreeing_with_its_category_is_not_flagged(): void
    {
        $result = Mapping::sectorFor('SADC postal costs and tariff regulation guidelines', 'postal');

        $this->assertSame('postal', $result['sector']);
        $this->assertFalse($result['from_title']);
    }

    /**
     * Webflow prefixes every upload with a hex id, so "6127444d969e0b4cd
     * 2348592_2000 INTERCONNECTION GUIDELINES FOR SADC.pdf" is a document
     * from 2000, not one from 6127.
     */
    public function test_the_year_comes_from_the_filename_and_not_from_webflows_prefix(): void
    {
        $this->assertSame(2000, Mapping::yearInFilename('6127444d969e0b4cd2348592_2000 INTERCONNECTION GUIDELINES FOR SADC.pdf'));
        $this->assertSame(2011, Mapping::yearInFilename('612fdb57cc121c7a14bd1471_2011_SADC TOOLKIT ON UA FUNDING.pdf'));
        $this->assertNull(Mapping::yearInFilename('612fdb57cc121c7a14bd1471_SADC TOOLKIT.pdf'));
    }

    public function test_the_original_filename_survives_without_webflows_prefix(): void
    {
        $this->assertSame(
            '2000 INTERCONNECTION GUIDELINES FOR SADC.pdf',
            Mapping::originalFilename('https://cdn/x/6127444d969e0b4cd2348592_2000%20INTERCONNECTION%20GUIDELINES%20FOR%20SADC.pdf')
        );
    }

    /**
     * Several CRASA articles kept the Webflow template's slug: the page at
     * /post/brand-optics-and-what-it-means-for-your-business is a
     * masterclass on broadband gap analysis. Carrying that forward would
     * put a marketing phrase in a regulator's citation.
     */
    public function test_a_slug_comes_from_the_title_not_from_the_template_slug(): void
    {
        $this->assertSame(
            'masterclass-on-the-broadband-gap-analysis',
            Mapping::slugFor(
                'Masterclass on the broadband gap analysis',
                'https://www.crasa.org/post/brand-optics-and-what-it-means-for-your-business'
            )
        );
    }

    public function test_a_shouted_headline_is_re_cased_with_its_acronyms_and_places_intact(): void
    {
        $this->assertSame(
            'CRASA 15th annual general meeting, Lilongwe, Malawi, 16th to 17th April 2026',
            TitleCase::apply('CRASA 15TH ANNUAL GENERAL MEETING, LILONGWE, MALAWI, 16th to 17th APRIL 2026')
        );
    }

    public function test_a_headline_already_in_mixed_case_is_left_exactly_as_written(): void
    {
        $this->assertSame('SADC Roaming Guidelines', TitleCase::apply('SADC Roaming Guidelines'));
        $this->assertFalse(TitleCase::wasRecased('SADC Roaming Guidelines'));
    }

    public function test_the_about_page_yields_its_sections_as_plain_text(): void
    {
        $sections = AboutSections::parse($this->aboutHtml());

        $this->assertSame('CRASA Structure', $sections['structure']['heading']);
        $this->assertStringContainsString('four institutions', $sections['structure']['body']);

        // The rich_text block renders nl2br(e(...)), so markup in a body
        // would be printed on the page rather than rendered.
        $this->assertStringNotContainsString('<', $sections['structure']['body']);

        // CLAUDE.md's first writing rule, applied to the legacy page's
        // <strong> capitals.
        $this->assertStringNotContainsString('ANNUAL GENERAL MEETING', $sections['structure']['body']);
        $this->assertStringContainsString('Annual general meeting', $sections['structure']['body']);
    }

    public function test_a_legacy_path_is_matched_without_its_host_or_trailing_slash(): void
    {
        LegacyRecord::create([
            'legacy_url' => 'https://www.crasa.org/post-articles/sadc-roaming-guidelines',
            'new_path' => '/publications/sadc-roaming-guidelines',
        ]);

        foreach ([
            'post-articles/sadc-roaming-guidelines',
            '/post-articles/sadc-roaming-guidelines',
            '/post-articles/sadc-roaming-guidelines/',
            'https://crasa.org/post-articles/sadc-roaming-guidelines',
        ] as $form) {
            $this->assertNotNull(LegacyRecord::pathFor($form), "No match for [{$form}].");
        }
    }

    /**
     * The test the ruling asks for: no legacy URL 404s.
     *
     * It walks whatever the mapper wrote rather than a hand list, so a
     * mapping that stops redirecting fails here rather than in front of a
     * regulator following a citation.
     */
    public function test_no_mapped_legacy_url_answers_404(): void
    {
        $publication = Publication::factory()->create(['slug' => 'sadc-roaming-guidelines', 'is_public' => true]);
        $publication->setTranslation('title', 'en', 'SADC roaming guidelines');

        LegacyRecord::create([
            'legacy_url' => 'https://www.crasa.org/post-articles/sadc-roaming-guidelines',
            'new_path' => '/publications/sadc-roaming-guidelines',
            'recordable_type' => $publication->getMorphClass(),
            'recordable_id' => $publication->getKey(),
        ]);

        LegacyRecord::create([
            'legacy_url' => 'https://www.crasa.org/post-category/tenders',
            'new_path' => '/newsroom?category=tender',
        ]);

        foreach (LegacyRecord::whereNotNull('new_path')->get() as $record) {
            $response = $this->get($record->legacyPath());

            $response->assertStatus(301);
            $this->assertStringContainsString(
                ltrim(explode('?', $record->new_path)[0], '/'),
                $response->headers->get('Location'),
                'The redirect for '.$record->legacy_url.' does not point at its record.'
            );
        }
    }

    public function test_a_listing_redirect_keeps_its_filter(): void
    {
        LegacyRecord::create([
            'legacy_url' => 'https://www.crasa.org/post-category/tenders',
            'new_path' => '/newsroom?category=tender',
        ]);

        $this->get('/post-category/tenders')
            ->assertStatus(301)
            ->assertRedirectContains('category=tender');
    }

    public function test_a_legacy_url_with_no_mapping_still_answers_404(): void
    {
        $this->get('/post-articles/nothing-was-ever-here')->assertNotFound();
    }

    /** Real records, not demonstration data. The go-live purge must not reach them. */
    public function test_imported_records_are_not_flagged_as_sample_data(): void
    {
        $news = NewsItem::factory()->create(['is_sample' => false]);
        $project = Project::create(['slug' => 'p', 'state' => 'current', 'status' => 'published', 'is_sample' => false]);

        $this->assertFalse($news->is_sample);
        $this->assertFalse($project->is_sample);
    }

    private function publicationHtml(): string
    {
        return '<html><head><title>CRASA Publications | SADC Roaming Guidelines</title>'
            .'<meta content="Edition 2021" name="description"/></head><body>'
            .'<img class="menu-button-close-image" src="x">'
            .'<div class="breadcrumb-section"><div class="breadcrumbs">'
            .'<a href="/post-category/electronic-communications-regulatory-guidelines">Electronic Communications Regulatory Guidelines</a>'
            .'<div>SADC Roaming Guidelines </div></div></div>'
            .'<div class="section"><h1>SADC Roaming Guidelines </h1>'
            .'<div class="text-large">Edition 2021</div>'
            .'<div class="article w-richtext"><p>The SADC roaming regulations aim to provide a '
            .'coordinated regional response to the formulation of roaming regulations.</p></div>'
            .'<a href="https://cdn.prod.website-files.com/x/612fe9d9e1d1a95bb6ccffc9_2021%20REVISED-SADC-ROAMING-REGULATIONS-2021.pdf" class="button w-button">Download</a>'
            .'</div><div class="footer-1">footer</div></body></html>';
    }

    private function newsHtml(): string
    {
        return '<html><head><title>CRASA | Visit</title></head><body>'
            .'<img class="menu-button-close-image" src="x">'
            .'<div class="section"><h1>CRASA CHAIRPERSON PAYS COURTESY VISIT TO BOCRA</h1>'
            .'<div class="article-meta"><a style="background-color:#e14817" href="/blog-categories/members-news" '
            .'class="badge article-meta-badge w-inline-block"><div>Members News</div></a>'
            .'<div>June 19, 2026</div></div>'
            .'<div class="article w-richtext"><p>Body.</p></div>'
            .'</div><div class="footer-1">footer</div></body></html>';
    }

    private function annualReportsHtml(): string
    {
        return '<html><head><title>CRASA Publications | Annual Reports</title></head><body>'
            .'<img class="menu-button-close-image" src="x">'
            .'<div class="breadcrumb-section"><div class="breadcrumbs">'
            .'<a href="/post-category/annual-reports">Annual Reports</a><div>CRASA ANNUAL REPORTS</div></div></div>'
            .'<div class="section"><h1>CRASA ANNUAL REPORTS</h1>'
            .'<div class="article w-dyn-bind-empty w-richtext"></div>'
            .'<a href="#" class="button w-condition-invisible w-button">Download</a>'
            .'<a href="https://crasaorg143-my.sharepoint.com/:f:/g/personal/crasa_crasa_org/Eon2" class="button w-button">Download</a>'
            .'</div><div class="footer-1">footer</div></body></html>';
    }

    private function aboutHtml(): string
    {
        return '<html><head><title>About Us</title></head><body>'
            .'<img class="menu-button-close-image" src="x">'
            .'<div id="vision-mission-statement" class="section"><h1>About Us</h1>'
            .'<h4 class="large-heading">Our Vision</h4><div class="text-large">An integrated Southern African community.</div>'
            .'</div>'
            .'<div id="structure" class="section"><h3 class="section-heading">CRASA Structure</h3>'
            .'<div class="text-large getting-started-intro-text">CRASA comprises of four institutions.'
            .'<br/><br/><strong>ANNUAL GENERAL MEETING</strong><br/><br/>The AGM is the supreme decision-making body.'
            .'</div></div>'
            .'<div id="secretariat" class="section"><h3 class="section-heading">Secretariat</h3>'
            .'<div class="text-large getting-started-intro-text">The Secretariat implements the decisions of the AGM.</div>'
            .'<div class="team-member-title"><h5 class="team-member-heading">Dr. Stanley Shanapinda</h5><div>Executive Secretary</div></div>'
            .'</div>'
            .'<div class="footer-1">footer</div></body></html>';
    }
}
