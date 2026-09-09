<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every inline svg states its own size.
 *
 * AN SVG WITH NO WIDTH AND NO HEIGHT FILLS ITS CONTAINER. That is the
 * default, it is silent, and it looks fine until the one page where the
 * container is the width of the viewport. The audit log's pagination
 * chevrons rendered at hundreds of pixels: Laravel's default paginator view
 * is the Tailwind one and its chevrons are <svg class="w-5 h-5">, two
 * classes that do not exist on a platform CLAUDE.md forbids Tailwind on.
 *
 * The same class of defect took the region map's insets earlier in the
 * build. Twice is a pattern, so it is a test rather than a fix.
 *
 * WIDTH AND HEIGHT ATTRIBUTES, NOT CSS. CSS can be overridden, can fail to
 * load, and can be beaten on specificity; the attributes are what the
 * browser lays out with before a stylesheet arrives.
 */
class InlineSvgSizeTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    /**
     * Every <svg> on a page, with the offenders named.
     *
     * @return list<string>
     */
    private function undimensioned(string $html, string $url): array
    {
        // Blade comments first. Two of the files this scans describe the
        // bug in prose, and a guard that trips on its own explanation of
        // why it exists is a guard nobody keeps.
        $html = preg_replace('/\{\{--.*?--\}\}/s', '', $html);

        preg_match_all('/<svg\b[^>]*>/i', $html, $matches);

        $offenders = [];

        foreach ($matches[0] as $tag) {
            preg_match('/\swidth\s*=\s*"([^"]+)"/i', $tag, $width);
            $hasHeight = preg_match('/\sheight\s*=\s*"[^"]+"/i', $tag) === 1;

            if ($width !== [] && $hasHeight) {
                continue;
            }

            /*
             * THE ONE EXCEPTION, and it is narrow.
             *
             * A percentage width with a viewBox is a deliberately
             * responsive drawing: the browser takes the height from the
             * aspect ratio, so it scales rather than expanding. The region
             * map is drawn that way on purpose. An svg with no width at
             * all has no such floor and fills whatever contains it.
             */
            if ($width !== []
                && str_ends_with(trim($width[1]), '%')
                && preg_match('/\sviewBox\s*=\s*"[^"]+"/i', $tag) === 1) {
                continue;
            }

            $offenders[] = $url.' -> '.substr(preg_replace('/\s+/', ' ', $tag), 0, 110);
        }

        return $offenders;
    }

    /** @return list<array{string}> */
    public static function publicPages(): array
    {
        return array_map(fn (string $url) => [$url], [
            '/en', '/en/about', '/en/members', '/en/members/bw', '/en/committees',
            '/en/publications', '/en/newsroom', '/en/events', '/en/contact',
            '/en/comparator', '/en/search?q=roaming', '/en/privacy', '/en/terms',
            '/en/accessibility',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicPages')]
    public function test_no_public_page_serves_an_svg_without_a_size(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame([], $this->undimensioned($html, $url));
    }

    /** @return list<array{string}> */
    public static function administrationPages(): array
    {
        $urls = [
            '/en/admin', '/en/admin/pages', '/en/admin/audit', '/en/admin/media',
            '/en/admin/media/upload', '/en/admin/menus', '/en/admin/translations',
            '/en/admin/submissions', '/en/admin/reports', '/en/admin/confirmations',
            '/en/admin/users', '/en/admin/users/invite', '/en/admin/meetings',
            '/en/admin/settings', '/en/admin/samples', '/en/admin/help',
            '/en/admin/data', '/en/admin/search?q=roaming',
        ];

        foreach (array_keys(\App\Admin\ResourceRegistry::all()) as $resource) {
            $urls[] = '/en/admin/data/'.$resource;
        }

        return array_map(fn (string $url) => [$url], $urls);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('administrationPages')]
    public function test_no_administration_page_serves_an_svg_without_a_size(string $url): void
    {
        $html = $this->actingAs($this->administrator)->get($url)->assertOk()->getContent();

        $this->assertSame([], $this->undimensioned($html, $url));
    }

    public function test_no_extranet_page_serves_an_svg_without_a_size(): void
    {
        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $rep->committees()->attach($committee->id, [
            'role' => CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        $offenders = [];

        foreach ([
            '/en/extranet/dashboard', '/en/extranet/folders', '/en/extranet/calendar',
            '/en/extranet/committees/'.$committee->slug, '/en/submissions', '/en/completeness',
        ] as $url) {
            $html = $this->actingAs($rep)->get($url)->assertOk()->getContent();
            $offenders = array_merge($offenders, $this->undimensioned($html, $url));
        }

        $this->assertSame([], $offenders);
    }

    /**
     * The paginator specifically, because it is where this was found and
     * because it is a vendor view somebody could republish.
     */
    public function test_the_paginator_is_ours_and_carries_no_tailwind(): void
    {
        foreach (['default', 'simple-default'] as $view) {
            $path = resource_path('views/vendor/pagination/'.$view.'.blade.php');

            $this->assertFileExists($path, 'Laravel\'s Tailwind paginator would be used instead.');

            $source = file_get_contents($path);

            $this->assertSame([], $this->undimensioned($source, $view));
            // Comments stripped: this file explains the bug by quoting the
            // markup that caused it.
            $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            $this->assertStringNotContainsString('class="w-5', $markup);
            $this->assertStringNotContainsString('dark:', $markup);
        }
    }

    /** And a page that actually paginates renders it at a sane size. */
    public function test_a_paginated_listing_serves_sized_chevrons(): void
    {
        // Enough rows to page. Written directly: the audit log is append
        // only and has no factory, which is the right shape for it.
        for ($i = 0; $i < 60; $i++) {
            \App\Models\AuditLog::create([
                'user_id' => $this->administrator->id,
                'action' => \App\Models\AuditLog::UPDATED,
                'auditable_type' => \App\Models\Country::class,
                'auditable_id' => 1,
                'changes' => ['n' => $i],
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $html = $this->actingAs($this->administrator)->get('/en/admin/audit')->assertOk()->getContent();

        $this->assertStringContainsString('c-pager__links', $html);
        $this->assertSame([], $this->undimensioned($html, '/en/admin/audit'));
    }
}
