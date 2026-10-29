<?php

namespace Tests\Feature;

use App\Models\NewsCategory;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\PublicationType;
use App\Models\Sector;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * News categories and publication types moved from enums into tables on
 * 29 October 2026 (Day 3, taxo). The public site must read the same after
 * the move.
 *
 * BEFORE is written out below: the enum cases in their declared order,
 * with the labels lang/{en,fr,pt}/news.php and publications.php gave them,
 * and the sectors in the order the filters used (by English name). The
 * public filters are read off the rendered pages after the migration and
 * compared with it, in all three languages.
 *
 * The lang keys the enums read are still in the lang files, so the seeded
 * names are also checked against them directly: if either side changes,
 * one of these tests says so.
 */
class TaxonomyMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** App\Enums\NewsCategory::cases(), as of 29 October 2026. */
    private const CATEGORIES_BEFORE = [
        'news' => ['en' => 'News', 'fr' => 'Actualité', 'pt' => 'Notícia'],
        'tender' => ['en' => 'Tender', 'fr' => 'Appel d\'offres', 'pt' => 'Concurso'],
        'consultation' => ['en' => 'Public consultation', 'fr' => 'Consultation publique', 'pt' => 'Consulta pública'],
        'communique' => ['en' => 'Communique', 'fr' => 'Communiqué', 'pt' => 'Comunicado'],
        'press_release' => ['en' => 'Press release', 'fr' => 'Communiqué de presse', 'pt' => 'Comunicado de imprensa'],
    ];

    /** App\Enums\PublicationType::cases(), as of 29 October 2026. */
    private const TYPES_BEFORE = [
        'annual_report' => ['en' => 'Annual report', 'fr' => 'Rapport annuel', 'pt' => 'Relatório anual'],
        'guideline' => ['en' => 'Guideline', 'fr' => 'Ligne directrice', 'pt' => 'Diretriz'],
        'study' => ['en' => 'Study', 'fr' => 'Étude', 'pt' => 'Estudo'],
        'position_paper' => ['en' => 'Position paper', 'fr' => 'Document de position', 'pt' => 'Documento de posição'],
        'communique' => ['en' => 'Communique', 'fr' => 'Communiqué', 'pt' => 'Comunicado'],
        'minutes' => ['en' => 'Minutes', 'fr' => 'Procès-verbal', 'pt' => 'Ata'],
    ];

    /** Sector::orderBy('name'), as the newsroom and library listed them. */
    private const SECTORS_BEFORE = [
        'broadcasting' => ['en' => 'Broadcasting', 'fr' => 'Radiodiffusion', 'pt' => 'Radiodifusão'],
        'postal' => ['en' => 'Postal', 'fr' => 'Services postaux', 'pt' => 'Serviços postais'],
        'telecommunications' => ['en' => 'Telecommunications', 'fr' => 'Télécommunications', 'pt' => 'Telecomunicações'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
    }

    public function test_the_tables_hold_exactly_the_enum_values_in_order(): void
    {
        $this->assertSame(array_keys(self::CATEGORIES_BEFORE), NewsCategory::query()->ordered()->pluck('slug')->all());
        $this->assertSame(array_keys(self::TYPES_BEFORE), PublicationType::query()->ordered()->pluck('slug')->all());
        $this->assertSame(array_keys(self::SECTORS_BEFORE), Sector::ordered()->pluck('slug')->all());

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (self::CATEGORIES_BEFORE as $slug => $names) {
                $this->assertSame($names[$locale], NewsCategory::bySlug($slug)->label($locale));
                $this->assertSame(trans('news.category_'.$slug, [], $locale), NewsCategory::bySlug($slug)->label($locale), $slug.' '.$locale);
            }

            foreach (self::TYPES_BEFORE as $slug => $names) {
                $this->assertSame($names[$locale], PublicationType::bySlug($slug)->label($locale));
                $this->assertSame(trans('publications.type_'.$slug, [], $locale), PublicationType::bySlug($slug)->label($locale), $slug.' '.$locale);
            }
        }
    }

    public function test_the_newsroom_filters_list_the_same_categories_and_sectors_as_before(): void
    {
        $sectors = Sector::all()->keyBy('slug');

        foreach (array_keys(self::CATEGORIES_BEFORE) as $i => $slug) {
            $sector = array_keys(self::SECTORS_BEFORE)[$i % 3];
            NewsItem::factory()->create(['category' => $slug, 'sector_id' => $sectors[$sector]->id]);
        }

        foreach (['en', 'fr', 'pt'] as $locale) {
            $html = $this->get('/'.$locale.'/newsroom')->assertOk()->getContent();

            $this->assertSame($this->expected(self::CATEGORIES_BEFORE, $locale), $this->facet($html, 'category'), 'newsroom categories, '.$locale);
            $this->assertSame($this->expected(self::SECTORS_BEFORE, $locale), $this->facet($html, 'sector'), 'newsroom sectors, '.$locale);
        }
    }

    public function test_the_library_filters_list_the_same_types_as_before(): void
    {
        foreach (array_keys(self::TYPES_BEFORE) as $slug) {
            Publication::factory()->create(['publication_type' => $slug, 'is_public' => true, 'external_url' => 'https://example.org/'.$slug]);
        }

        foreach (['en', 'fr', 'pt'] as $locale) {
            $html = $this->get('/'.$locale.'/publications')->assertOk()->getContent();

            $this->assertSame($this->expected(self::TYPES_BEFORE, $locale), $this->facet($html, 'type'), 'library types, '.$locale);
        }
    }

    public function test_an_item_shows_its_category_label_and_the_badge_colour_it_had(): void
    {
        $expected = [
            'news' => 'background: var(--c-navy); color: var(--c-white)',
            'press_release' => 'background: var(--c-amber-light); color: var(--c-amber-dark)',
            'tender' => 'background: var(--c-green-light); color: var(--c-green-dark)',
            'consultation' => 'background: var(--c-purple-light); color: var(--c-purple-dark)',
            'communique' => 'background: var(--c-grey-light); color: var(--c-charcoal)',
        ];

        // The pairs public/css/blocks.css drew for .n-badge--<slug>.
        $css = file_get_contents(public_path('css/blocks.css'));
        foreach ($expected as $slug => $style) {
            [$ground, $text] = array_map(fn ($part) => trim(explode(':', $part, 2)[1]), explode(';', $style));
            $this->assertMatchesRegularExpression(
                '/\.n-badge--'.str_replace('_', '-', $slug).' \{ background: '.preg_quote($ground, '/').'; color: '.preg_quote($text, '/').'; \}/',
                $css,
            );
            $this->assertSame($style, NewsCategory::bySlug($slug)->badgeStyle());
        }

        $item = NewsItem::factory()->create(['category' => 'press_release']);
        $this->get('/fr/newsroom/'.$item->slug)->assertOk()->assertSee('Communiqué de presse');
    }

    /** @return list<array{0: string, 1: string}> */
    private function expected(array $before, string $locale): array
    {
        return collect($before)->map(fn ($names, $slug) => [(string) $slug, $names[$locale]])->values()->all();
    }

    /** @return list<array{0: string, 1: string}> value and label of each option, in page order */
    private function facet(string $html, string $field): array
    {
        preg_match_all(
            '#name="'.preg_quote($field, '#').'\[\]"\s+value="([^"]*)"[^>]*>\s*<span class="c-facet__name">([^<]*)</span>#',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(fn ($m) => [$m[1], html_entity_decode($m[2], ENT_QUOTES)], $matches);
    }
}
