<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * No reader ever sees a tag.
 *
 * THIS IS THE TEST THAT WAS MISSING. The platform served
 * "&lt;p&gt;Digital transformation…" to anybody who opened a consultation
 * notice, in all three languages, and nothing failed. Every other test
 * asserted that particular strings were present; none asserted that markup
 * was absent from what a reader actually sees.
 *
 * It walks the rendered response, removes the markup that is meant to be
 * there, and asserts that what is left carries no tags, no attributes and
 * no shortcodes. A template that starts escaping its content again fails
 * here rather than in front of a regulator.
 */
class RenderedMarkupTest extends TestCase
{
    use RefreshDatabase;

    /** Things that must never appear in text a reader can see. */
    private const FORBIDDEN = ['<p>', '<p ', '</p>', '<div', '<a ', '</a>', 'href=', '<sup>', '<img', '<figure', '<span', '[vc_', '[/vc_', '&lt;', 'onclick=', 'javascript:'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
    }

    /**
     * What a reader sees: the response with its own markup taken off.
     *
     * A textarea's contents are source somebody is editing, not rendered
     * output, so those come out too.
     */
    private function visible(string $html): string
    {
        $html = preg_replace('#<(script|style|textarea)\b.*?</\1>#is', '', $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertNoMarkupAt(string $path): void
    {
        $response = $this->get($path);

        $this->assertContains($response->status(), [200, 302, 404], $path.' errored.');

        if ($response->status() !== 200) {
            return;
        }

        $visible = $this->visible($response->getContent());

        foreach (self::FORBIDDEN as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $visible,
                $path.' shows "'.$needle.'" to a reader as text.'
            );
        }
    }

    /** The record from the report, the one a reader was reading tags on. */
    public function test_a_news_item_carrying_html_renders_it_in_every_locale(): void
    {
        $item = NewsItem::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);
        $item->setTranslation('title', 'en', 'Call for stakeholder comments');
        $item->setTranslation('body', 'en',
            '<p>Digital transformation may be described as the process by which operators embed digital technologies.</p>'
            .'<p>Comments by the 31<sup>st</sup> of July 2023 to <a href="mailto:crasa@crasa.org">crasa@crasa.org</a>.</p>');

        foreach (['en', 'fr', 'pt'] as $locale) {
            $path = "/{$locale}/newsroom/{$item->slug}";

            $this->assertNoMarkupAt($path);

            $html = $this->get($path)->getContent();

            // Not merely absent as text: present as markup.
            $this->assertStringContainsString('<p>Digital transformation', $html, $path.' lost its paragraphs.');
            $this->assertStringContainsString('31<sup>st</sup>', $html, $path.' lost its superscript.');
            $this->assertStringContainsString('href="mailto:crasa@crasa.org"', $html, $path.' lost its mailto link.');
        }
    }

    public function test_a_meeting_agenda_with_shortcodes_renders_in_every_locale(): void
    {
        $meeting = Meeting::create([
            'title' => 'A committee meeting',
            'slug' => 'a-committee-meeting',
            'starts_on' => now()->addMonth(),
            'clearance' => 'public',
            'agenda' => '[vc_row][vc_column width="1/2"][vc_column_text] Opening and adoption of the agenda. [/vc_column_text][vc_empty_space][/vc_column][/vc_row]',
            'is_sample' => false,
        ]);

        foreach (['en', 'fr', 'pt'] as $locale) {
            $path = "/{$locale}/events/{$meeting->slug}";
            $this->assertNoMarkupAt($path);
            $this->get($path)->assertSee('Opening and adoption of the agenda', false);
        }
    }

    /**
     * The four agendas that came across as the theme's demo Latin. The
     * titles and dates are genuine; only the agenda body is filler.
     */
    public function test_a_filler_agenda_shows_a_localised_notice_and_never_the_latin(): void
    {
        $meeting = Meeting::create([
            'title' => '1st Universal Service and Access Committee Meeting',
            'slug' => 'filler-agenda-meeting',
            'starts_on' => now()->addMonth(),
            'clearance' => 'public',
            'agenda' => '[vc_row][vc_column][vc_column_text] Alienum phaedrum torquatos nec eu, vis detraxit periculis ex. [/vc_column_text][/vc_row]',
            'is_sample' => false,
        ]);

        foreach (['en' => 'Agenda to be confirmed.', 'fr' => 'Ordre du jour à confirmer.', 'pt' => 'Agenda a confirmar.'] as $locale => $notice) {
            $response = $this->get("/{$locale}/events/{$meeting->slug}")->assertOk();

            $response->assertSee($notice, false);
            $response->assertDontSee('Alienum phaedrum', false);
            $response->assertDontSee('torquatos', false);

            // The meeting itself is still a real meeting.
            $response->assertSee('1st Universal Service and Access Committee Meeting', false);
        }
    }

    /** The stored value is never touched, so it can be restored by doing nothing. */
    public function test_rendering_never_changes_what_is_stored(): void
    {
        $original = '[vc_row][vc_column_text] Kept exactly. [/vc_column_text][/vc_row]';

        $meeting = Meeting::create([
            'title' => 'Untouched', 'slug' => 'untouched', 'starts_on' => now()->addMonth(),
            'clearance' => 'public', 'agenda' => $original, 'is_sample' => false,
        ]);

        $this->get('/en/events/untouched')->assertOk();
        $this->get('/fr/events/untouched')->assertOk();

        $this->assertSame($original, $meeting->fresh()->agenda, 'Rendering wrote to the database.');
    }

    /** Two press releases carry a bracketed dateline that is not a shortcode. */
    public function test_a_bracketed_dateline_survives_on_the_page(): void
    {
        $item = NewsItem::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);
        $item->setTranslation('title', 'en', 'A press release');
        $item->setTranslation('body', 'en', '<p>[Gaborone, 2nd June 2026] The Association announced today.</p>');

        $this->get("/en/newsroom/{$item->slug}")
            ->assertOk()
            ->assertSee('[Gaborone, 2nd June 2026]', false);
    }

    public function test_dangerous_markup_in_a_record_never_reaches_the_page(): void
    {
        $project = Project::create([
            'slug' => 'a-project', 'state' => 'current', 'status' => 'published',
            'published_at' => now()->subDay(), 'is_sample' => false,
        ]);
        $project->setTranslation('title', 'en', 'A project');
        $project->setTranslation('body', 'en',
            '<p onclick="steal()">Body</p><script>alert(1)</script><a href="javascript:alert(1)">Trap</a>');

        $html = $this->get('/en/projects/a-project')->assertOk()->getContent();

        // Unique to the injection, so the whole document is fair to assert
        // against. The layout's own has-js snippet is a <script> tag and a
        // legitimate one, which is why <script> is checked inside the
        // content region rather than across the page.
        foreach (['alert(1)', 'onclick', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $html, 'A project page served '.$bad.'.');
        }

        $start = strpos($html, 'c-prose');
        $this->assertNotFalse($start, 'The project body did not render at all.');
        $prose = substr($html, $start, (strpos($html, '</div>', $start) ?: $start) - $start + 6);

        $this->assertStringNotContainsString('<script', $prose);
        $this->assertStringContainsString('Body', $html);
    }

    public function test_no_public_listing_or_detail_page_shows_markup(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (['newsroom', 'publications', 'events', 'projects', 'members', 'committees', 'contact'] as $section) {
                $this->assertNoMarkupAt("/{$locale}/{$section}");
            }
        }
    }

    /* The static guard ------------------------------------------------------- */

    /**
     * No template prints a stored value directly.
     *
     * The three faults this replaced were three templates each improvising
     * an answer. This is what stops a fourth.
     */
    public function test_no_template_renders_a_database_value_unescaped(): void
    {
        $allowed = [
            // Generated in the controller, never stored: an SVG QR code.
            'pages/auth/two-factor-enrol.blade.php',
            // Stripped, escaped, then marked. See DatabaseSearchDriver.
            'pages/search/index.blade.php',
            // A column the admin table builds itself from escaped parts.
            'components/admin/resource-table.blade.php',
        ];

        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $relative = str_replace(resource_path('views').'/', '', $file->getPathname());

            if (in_array($relative, $allowed, true)) {
                continue;
            }

            // Comments explain the rule; they must not trip it.
            $contents = preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents()) ?? '';

            preg_match_all('/\{!!(.*?)!!\}/s', $contents, $matches);

            foreach ($matches[1] as $expression) {
                $expression = trim($expression);

                $safe = str_contains($expression, 'ContentRenderer')
                    || str_contains($expression, '$rendered')
                    // A template that binds $renderer to the ContentRenderer
                    // and prints through it.
                    || (str_starts_with($expression, '$renderer->render(')
                        && str_contains($contents, '$renderer = app(\\App\\Services\\ContentRenderer::class)'))
                    || str_starts_with($expression, '__(');

                if (! $safe) {
                    $offenders[] = $relative.': {!! '.\Illuminate\Support\Str::limit($expression, 60).' !!}';
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A template prints a value without the content renderer:\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_the_audit_command_reports_without_writing_anything(): void
    {
        $meeting = Meeting::create([
            'title' => 'Audited', 'slug' => 'audited', 'starts_on' => now()->addMonth(),
            'clearance' => 'public', 'agenda' => '[vc_row][vc_column_text] Text [/vc_column_text][/vc_row]',
            'is_sample' => false,
        ]);

        $before = $meeting->agenda;

        $this->artisan('content:audit')->assertSuccessful();

        $this->assertSame($before, $meeting->fresh()->agenda);
        $this->assertNotEmpty(Storage::disk('local')->files('content-audit'));
    }
}
