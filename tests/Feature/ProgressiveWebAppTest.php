<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Country;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\Publication;
use App\Models\User;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WS-02, screen 15.
 *
 * Most of what matters here is the security boundary rather than the
 * caching. A cache is a copy that outlives the session that fetched it, and
 * a delegate's phone is a device that gets lost and shared. Screen 15 states
 * the rule: "Documents graded Confidential or Secretariat ... require a live
 * authenticated session, so they cannot be left on a device that is later
 * lost."
 */
class ProgressiveWebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    /* The manifest ------------------------------------------------------------ */

    public function test_the_manifest_is_served_in_every_language(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $manifest = $this->get('/'.$locale.'/manifest.webmanifest')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/manifest+json')
                ->json();

            // The reader's own language, which is why this is a route and
            // not a static file. A fixed start URL installs an English app
            // on a Portuguese reader's phone.
            $this->assertSame('http://localhost/'.$locale, $manifest['start_url']);
            $this->assertSame($locale, $manifest['lang']);
            $this->assertSame('standalone', $manifest['display']);
        }
    }

    public function test_the_manifest_colours_come_from_the_tokens(): void
    {
        $manifest = $this->get('/en/manifest.webmanifest')->assertOk()->json();
        $tokens = File::get(public_path('css/tokens.css'));

        // A splash screen in a colour the site does not use is the tell that
        // a manifest was filled in from a generator.
        $this->assertStringContainsStringIgnoringCase($manifest['theme_color'], $tokens);
        $this->assertStringContainsStringIgnoringCase($manifest['background_color'], $tokens);
    }

    public function test_every_icon_the_manifest_names_exists_at_the_size_it_claims(): void
    {
        $manifest = $this->get('/en/manifest.webmanifest')->assertOk()->json();

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(parse_url($icon['src'], PHP_URL_PATH));

            $this->assertFileExists($path, $icon['src'].' is named in the manifest and does not exist.');

            [$width, $height] = getimagesize($path);
            [$claimed] = explode('x', $icon['sizes']);

            $this->assertSame((int) $claimed, $width, $icon['src'].' claims '.$icon['sizes']);
            $this->assertSame((int) $claimed, $height);
        }
    }

    /**
     * Without a maskable icon Android puts the square one inside a white
     * rounded box, which looks like a screenshot of an icon rather than an
     * icon.
     */
    public function test_a_maskable_icon_is_offered(): void
    {
        $manifest = $this->get('/en/manifest.webmanifest')->assertOk()->json();

        $purposes = collect($manifest['icons'])->pluck('purpose');

        $this->assertTrue($purposes->contains('maskable'));
        $this->assertTrue($purposes->contains('any'));
    }

    /** iOS ignores the manifest's icons and reads this instead. */
    public function test_the_apple_touch_icon_is_linked_and_exists(): void
    {
        $this->get('/en/publications')->assertOk()->assertSee('apple-touch-icon', false);
        $this->assertFileExists(public_path('img/app/apple-touch-icon.png'));
    }

    public function test_every_page_links_the_manifest_in_its_own_language(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get('/'.$locale.'/publications')
                ->assertOk()
                ->assertSee('/'.$locale.'/manifest.webmanifest', false);
        }
    }

    /* The offline page -------------------------------------------------------- */

    public function test_the_offline_page_exists_in_every_language(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get('/'.$locale.'/offline')->assertOk();
        }
    }

    /**
     * A reader who is offline on a shared device is entitled to know what is
     * on that device. The promise is stated where the person affected can
     * read it.
     */
    public function test_the_offline_page_states_what_is_never_kept(): void
    {
        $this->get('/en/offline')
            ->assertOk()
            ->assertSee('Never kept')
            ->assertSee('Confidential')
            // Screen 15's stale-aware rule, in the reader's terms.
            ->assertSee('Possibly out of date');
    }

    /* The service worker ------------------------------------------------------ */

    public function test_the_service_worker_is_served_from_the_root(): void
    {
        // Scope. A worker served from a subdirectory cannot control the
        // whole site, and there is no header on this host to widen it.
        $this->assertFileExists(public_path('sw.js'));
    }

    /**
     * THE RULE THIS EXISTS TO ENFORCE.
     *
     * Nothing behind a sign-in is cached, served from cache, or read. A
     * cached Confidential document on a shared device defeats the zero-trust
     * requirement, and a delegate's phone is shared and lost.
     */
    public function test_the_worker_never_touches_anything_behind_a_sign_in(): void
    {
        $worker = File::get(public_path('sw.js'));

        foreach (['/admin', '/extranet', '/submissions', '/account', '/login', '/two-factor'] as $prefix) {
            $this->assertStringContainsString(
                "'".$prefix."'",
                $worker,
                $prefix.' is behind a sign-in and the worker does not exclude it.'
            );
        }

        // The locale segment sits in front of every path, so a prefix test
        // that anchored on the start of the string would match nothing.
        $this->assertStringContainsString("replace(/^\\/[a-z]{2}(?=\\/|$)/, '')", $worker);
    }

    /**
     * A document is kept only when the server says it may be. The worker
     * requires an explicit "public", not merely the absence of a
     * prohibition, because only the server knows the clearance.
     */
    public function test_a_document_may_only_be_kept_when_the_server_declares_it_public(): void
    {
        $worker = File::get(public_path('sw.js'));

        $this->assertStringContainsString('function isKeepable', $worker);
        $this->assertStringContainsString('isKeepable(response)', $worker);
        // no-store is the header the document controller sends for anything
        // above public clearance.
        $this->assertStringContainsString('no-store', $worker);
    }

    /**
     * The header the worker relies on. If the controller stopped sending it,
     * the worker would start keeping restricted files and nothing would say
     * so — which is why this asserts the server side rather than the client.
     */
    public function test_the_server_marks_a_restricted_document_as_never_stored(): void
    {
        $this->seed(PublicationSeeder::class);

        $public = Document::where('clearance', Clearance::Public->value)->firstOrFail();

        $this->get('/en/documents/'.$public->id.'/download')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public');

        $instrument = Instrument::factory()->inForce()->create(['slug' => 'restricted-annex']);
        $restricted = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'clearance' => Clearance::Confidential,
            'file_path' => 'documents/restricted-annex.pdf',
            'original_filename' => 'restricted-annex.pdf',
        ]);
        Storage::disk('local')->put($restricted->file_path, '%PDF-1.4');

        $admin = User::factory()->administrator()->create();

        $header = $this->actingAs($admin)
            ->get('/en/documents/'.$restricted->id.'/download')
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $header, 'A Confidential document may be stored on a device.');
        $this->assertStringNotContainsString('public', $header);
    }

    /**
     * A member state representative refused a document must not be able to
     * save it either. The refusal happens before any cache is involved.
     */
    public function test_a_refused_document_cannot_be_saved_because_it_cannot_be_fetched(): void
    {
        $instrument = Instrument::factory()->inForce()->create(['slug' => 'closed-annex']);
        $document = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'clearance' => Clearance::Confidential,
            'file_path' => 'documents/closed-annex.pdf',
            'original_filename' => 'closed-annex.pdf',
        ]);
        Storage::disk('local')->put($document->file_path, '%PDF-1.4');

        $rep = User::factory()->memberStateRep(Country::first())->create();

        $this->actingAs($rep)->get('/en/documents/'.$document->id.'/download')->assertForbidden();
    }

    /* The install prompt ------------------------------------------------------ */

    /**
     * Screen 15: "Shown once, on the second visit, never as an interstitial
     * over content." A prompt on the first visit asks a stranger to install
     * something; a prompt over content is an advertisement.
     */
    public function test_the_install_prompt_is_a_band_and_starts_hidden(): void
    {
        $html = $this->get('/en/publications')->assertOk()->getContent();

        $this->assertStringContainsString('data-install', $html);
        // Hidden in the markup, revealed by script on a second visit only.
        $this->assertMatchesRegularExpression('~class="c-install"[^>]*data-install[^>]*hidden~', $html);

        $script = File::get(public_path('js/app.js'));
        $this->assertStringContainsString('visits < 2', $script);
        $this->assertStringContainsString('crasa-install-dismissed', $script);
    }

    public function test_dismissing_the_prompt_is_remembered(): void
    {
        $script = File::get(public_path('js/app.js'));

        $this->assertStringContainsString("remember(DISMISSED, '1')", $script);
        $this->assertStringContainsString("recall(DISMISSED) === '1'", $script);
    }

    /** A button that does nothing is worse than a button that is not there. */
    public function test_the_save_control_is_hidden_until_a_worker_is_in_control(): void
    {
        $this->seed(PublicationSeeder::class);
        $publication = Publication::listed()->whereHas('documents')->firstOrFail();

        $this->get('/en/publications/'.$publication->slug)
            ->assertOk()
            ->assertSee('data-save-offline', false)
            ->assertSee('Save for offline');

        $this->assertStringContainsString('button.hidden = false', File::get(public_path('js/app.js')));
    }
}
