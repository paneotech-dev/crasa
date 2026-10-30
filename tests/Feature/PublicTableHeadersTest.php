<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\NewsItem;
use App\Models\PageBlock;
use App\Services\ContentRenderer;
use Database\Seeders\DatabaseSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day 2 request: "one page with a table missing headers". Every data
 * table on a public page states its column headers as th scope="col",
 * every th has a scope, and every table is named by a caption or by the
 * heading it points at. Read off the rendered HTML of each public page
 * that carries a table, and of content typed with a table in it.
 */
class PublicTableHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERLESS = '<p>Fees.</p><table><tbody><tr><td>Licence</td><td>Fee</td></tr><tr><td>Class A</td><td>100</td></tr></tbody></table>';

    /** @return list<string> one message per fault; the count of tables first */
    private function faults(string $html, string $where, int &$count): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $faults = [];

        /** @var DOMElement $table */
        foreach ($xpath->query('//table') as $i => $table) {
            $count++;
            $label = $where.' table '.($i + 1);

            if ($xpath->query('.//th[@scope="col"]', $table)->length === 0) {
                $faults[] = $label.': no th scope="col"';
            }
            foreach ($xpath->query('.//th[not(@scope)]', $table) as $th) {
                $faults[] = $label.': a th without scope ('.trim($th->textContent).')';
            }
            $named = $xpath->query('./caption', $table)->length > 0
                || $table->hasAttribute('aria-labelledby') || $table->hasAttribute('aria-label');
            if (! $named) {
                $faults[] = $label.': no caption and no name';
            }
        }

        return $faults;
    }

    public function test_every_table_on_the_public_pages_has_column_headers_and_a_name(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->travelTo('2026-10-29 10:00:00');

        $news = NewsItem::factory()->create(['slug' => 'fees-table']);
        $news->setTranslation('body', 'en', self::HEADERLESS);

        $iso = strtolower((string) Country::query()->value('iso_code'));
        $paths = ['/en', '/en/events', '/en/members', '/en/members/'.$iso, '/en/accessibility', '/en/comparator',
            '/en/about', '/en/committees', '/en/privacy', '/en/terms', '/en/newsroom/fees-table'];

        $count = 0;
        $faults = [];
        $withTables = [];
        foreach ($paths as $path) {
            $response = $this->get($path);
            $this->assertContains($response->getStatusCode(), [200], $path);
            $before = $count;
            $faults = array_merge($faults, $this->faults($response->getContent(), $path, $count));
            if ($count > $before) {
                $withTables[] = $path;
            }
        }

        $this->assertSame([], $faults);

        // The hero block's comparison slide (drawn when a slide has the
        // comparison panel and no photograph): two columns, headed for
        // screen readers.
        $hero = PageBlock::query()->where('block_type', 'hero')->firstOrFail();
        $settings = $hero->settings;
        $settings['rotation_items'] = [['cta_url' => '/comparator', 'panel' => 'comparison', 'image' => null]];
        $hero->settings = $settings;
        $home = view('blocks.hero', ['block' => $hero])->render();
        $this->assertMatchesRegularExpression('#<table class="b-hero__table">\s*<caption class="c-sr">[^<]+</caption>.*?<thead class="b-hero__table-head">\s*<tr>\s*<th scope="col"><span class="c-sr">Member state</span></th>\s*<th scope="col"><span class="c-sr">Value</span></th>#s', $home);
        // The pages known to carry tables did render them.
        foreach (['/en/events', '/en/members/'.$iso, '/en/comparator', '/en/accessibility', '/en/newsroom/fees-table'] as $path) {
            $this->assertContains($path, $withTables);
        }
    }

    public function test_content_with_a_headerless_table_gets_a_header_row(): void
    {
        $renderer = app(ContentRenderer::class);
        $html = $renderer->render(self::HEADERLESS)->toHtml();

        $this->assertStringContainsString('<thead><tr><th scope="col">Licence</th><th scope="col">Fee</th></tr></thead>', $html);
        $this->assertStringContainsString('<table><caption>Table: Licence, Fee</caption><thead>', $html);
        $this->assertStringContainsString('<tbody><tr><td>Class A</td><td>100</td></tr></tbody>', $html);
        // Idempotent: a second pass changes nothing.
        $this->assertSame($html, $renderer->render($html)->toHtml());
    }

    public function test_existing_headers_keep_their_place_and_gain_a_scope(): void
    {
        $renderer = app(ContentRenderer::class);

        $html = $renderer->render('<table><caption>Rates</caption><tr><th>State</th><th>Rate</th></tr><tr><th>Malawi</th><td>2</td></tr></table>')->toHtml();
        $this->assertStringContainsString('<caption>Rates</caption>', $html);
        $this->assertStringContainsString('<th scope="col">State</th><th scope="col">Rate</th>', $html);
        $this->assertStringContainsString('<th scope="row">Malawi</th><td>2</td>', $html);

        // A heading written just before the table names it.
        $headed = $renderer->render('<h3>Licence fees</h3><table><tr><td>Licence</td><td>Fee</td></tr><tr><td>A</td><td>1</td></tr></table>')->toHtml();
        $this->assertMatchesRegularExpression('#<h3 id="(table-heading-[0-9a-f]{10})">Licence fees</h3><table aria-labelledby="\1"><thead>#', $headed);
        $this->assertStringNotContainsString('<caption>', $headed);
        $this->assertSame($headed, $renderer->render($headed)->toHtml());

        // A one-row table has no header row to promote.
        $one = $renderer->render('<table><tr><td>Only</td></tr></table>')->toHtml();
        $this->assertStringNotContainsString('<th', $one);
    }
}
