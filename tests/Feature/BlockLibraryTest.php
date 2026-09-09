<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\Locales;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DATA_MODEL section 8. Twelve block types, each rendered with empty
 * settings, with full settings, and in all three locales.
 */
class BlockLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function block(BlockType $type, array $settings = [], array $text = []): PageBlock
    {
        $block = PageBlock::factory()->ofType($type, $settings)->create([
            'page_id' => Page::factory()->create()->id,
        ]);

        foreach ($text as $locale => $fields) {
            $block->setTranslations($locale, $fields);
        }

        return $block->fresh();
    }

    private function render(PageBlock $block): string
    {
        return View::make('components.block', ['block' => $block])->render();
    }

    /** @return array<string, array{BlockType}> */
    public static function blockTypes(): array
    {
        return collect(BlockType::cases())
            ->mapWithKeys(fn (BlockType $t) => [$t->value => [$t]])
            ->all();
    }

    /* Every type renders --------------------------------------------------- */

    #[DataProvider('blockTypes')]
    public function test_a_block_renders_with_empty_settings(BlockType $type): void
    {
        $html = $this->render($this->block($type, $type->defaultSettings()));

        $this->assertNotSame('', trim($html), $type->value.' rendered nothing at all.');
    }

    /**
     * Settings emptied out, rather than left at their defaults. Some types
     * have meaningful defaults: a social feed defaults to CRASA's own
     * accounts, so at its defaults it has content and correctly shows no
     * empty state. This strips the arrays to reach the genuinely empty case.
     *
     * @return array<string, mixed>
     */
    private function emptiedSettings(BlockType $type): array
    {
        return collect($type->defaultSettings())
            ->map(fn ($value) => is_array($value) ? [] : $value)
            ->all();
    }

    /**
     * Empty is never blank space. Every type with nothing to show renders an
     * empty state or a designed image slot that names what is missing.
     */
    #[DataProvider('blockTypes')]
    public function test_an_empty_block_shows_an_empty_state_rather_than_blank_space(BlockType $type): void
    {
        $html = $this->render($this->block($type, $this->emptiedSettings($type)));

        $hasEmptyState = str_contains($html, 'b__empty') || str_contains($html, 'b__slot');

        $this->assertTrue($hasEmptyState, $type->value.' renders blank space when it has nothing to show.');
    }

    #[DataProvider('blockTypes')]
    public function test_a_block_renders_in_all_three_locales(BlockType $type): void
    {
        $text = [];
        foreach ($type->translatableFields() as $field) {
            $text['en'][$field] = 'EN '.$field;
            $text['fr'][$field] = 'FR '.$field;
            $text['pt'][$field] = 'PT '.$field;
        }

        $block = $this->block($type, $type->defaultSettings(), $text);

        foreach (Locales::SUPPORTED as $locale) {
            app()->setLocale($locale);
            $html = $this->render($block);

            $this->assertNotSame('', trim($html), $type->value.' rendered nothing in '.$locale);

            if ($type->translatableFields() !== []) {
                $this->assertStringContainsString(
                    strtoupper($locale).' '.$type->primaryField(),
                    $html,
                    $type->value.' did not render its '.$locale.' text.'
                );
            }
        }
    }

    #[DataProvider('blockTypes')]
    public function test_a_block_falls_back_to_english_when_a_language_is_missing(BlockType $type): void
    {
        if ($type->translatableFields() === []) {
            $this->markTestSkipped('No translatable fields.');
        }

        // The field that carries the block, not merely its first one: a
        // hero with an eyebrow and no headline is an empty hero.
        $field = $type->primaryField();
        $block = $this->block($type, $type->defaultSettings(), ['en' => [$field => 'English only']]);

        app()->setLocale('pt');

        $this->assertStringContainsString('English only', $this->render($block));
    }

    /* Settings validation -------------------------------------------------- */

    /**
     * The converse: a block with content does not show an empty state.
     * An empty state that never goes away is as bad as blank space.
     */
    public function test_a_configured_social_feed_shows_accounts_not_an_empty_state(): void
    {
        $html = $this->render($this->block(BlockType::SocialFeed, ['networks' => ['x', 'linkedin']]));

        $this->assertStringContainsString('LinkedIn', $html);
        $this->assertStringNotContainsString('b__empty', $html);
    }

    /**
     * The figure is a numeric indicator and is not per-language. Its label
     * and its source are text and live in the translations table, so the
     * settings JSON carries the value alone.
     */
    public function test_a_configured_key_figures_block_shows_its_figures(): void
    {
        $block = $this->block(
            BlockType::KeyFigures,
            ['items' => [['value' => '14']]],
            ['en' => ['item_0_label' => 'Member regulators', 'item_0_source' => 'CRASA membership']]
        );

        $html = $this->render($block);

        $this->assertStringContainsString('14', $html);
        $this->assertStringContainsString('Member regulators', $html);
        $this->assertStringNotContainsString('b__empty', $html);
    }

    public function test_a_key_figure_label_is_translatable_but_the_figure_is_not(): void
    {
        $block = $this->block(
            BlockType::KeyFigures,
            ['items' => [['value' => '14']]],
            [
                'en' => ['item_0_label' => 'Member regulators'],
                'fr' => ['item_0_label' => 'Régulateurs membres'],
            ]
        );

        app()->setLocale('fr');
        $html = $this->render($block);

        $this->assertStringContainsString('Régulateurs membres', $html);
        // The number is the same in every language.
        $this->assertStringContainsString('14', $html);
        $this->assertStringNotContainsString('Member regulators', $html);
    }

    public function test_a_pillars_block_cannot_be_given_five_columns(): void
    {
        $this->expectException(ValidationException::class);

        $this->block(BlockType::Pillars, ['columns' => 5, 'items' => []]);
    }

    public function test_a_pillars_block_accepts_two_to_four_columns(): void
    {
        foreach ([2, 3, 4] as $columns) {
            $block = $this->block(BlockType::Pillars, ['columns' => $columns, 'items' => []]);
            $this->assertSame($columns, $block->setting('columns'));
        }
    }

    public function test_a_call_to_action_cannot_be_given_an_arbitrary_colour(): void
    {
        $this->expectException(ValidationException::class);

        $this->block(BlockType::CallToAction, ['tone' => '#615fff']);
    }

    public function test_a_call_to_action_accepts_only_the_defined_tones(): void
    {
        foreach (['navy', 'green', 'cream'] as $tone) {
            $block = $this->block(BlockType::CallToAction, ['tone' => $tone]);
            $this->assertSame($tone, $block->setting('tone'));
        }
    }

    public function test_a_comparison_cannot_pin_more_than_four_member_states(): void
    {
        $this->expectException(ValidationException::class);

        $this->block(BlockType::FeaturedComparison, ['country_ids' => [1, 2, 3, 4, 5]]);
    }

    public function test_a_social_feed_cannot_name_an_unknown_network(): void
    {
        $this->expectException(ValidationException::class);

        $this->block(BlockType::SocialFeed, ['networks' => ['myspace']]);
    }

    public function test_a_listing_limit_is_capped(): void
    {
        $this->expectException(ValidationException::class);

        $this->block(BlockType::NewsroomStrip, ['limit' => 500]);
    }

    /**
     * Validation is on the model, so it holds however the write arrives.
     */
    public function test_validation_holds_on_update_not_only_on_create(): void
    {
        $block = $this->block(BlockType::Pillars, ['columns' => 3, 'items' => []]);

        $this->expectException(ValidationException::class);

        $block->update(['settings' => ['columns' => 9]]);
    }

    /* Structure ------------------------------------------------------------ */

    public function test_block_text_lives_in_the_translations_table_not_in_settings(): void
    {
        $block = $this->block(BlockType::RichText, ['alignment' => 'start'], [
            'en' => ['heading' => 'What we do', 'body' => 'Harmonisation across fourteen member states.'],
        ]);

        $this->assertArrayNotHasKey('heading', $block->settings ?? []);
        $this->assertArrayNotHasKey('body', $block->settings ?? []);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => $block->getMorphClass(),
            'translatable_id' => $block->id,
            'field' => 'heading',
            'value' => 'What we do',
        ]);
    }

    public function test_a_page_orders_its_blocks_by_position(): void
    {
        $page = Page::factory()->create();

        PageBlock::factory()->ofType(BlockType::RichText)->create(['page_id' => $page->id, 'position' => 2]);
        PageBlock::factory()->ofType(BlockType::Hero)->create(['page_id' => $page->id, 'position' => 0]);
        PageBlock::factory()->ofType(BlockType::KeyFigures)->create(['page_id' => $page->id, 'position' => 1]);

        $this->assertSame(
            ['hero', 'key_figures', 'rich_text'],
            $page->blocks->map(fn ($b) => $b->type()->value)->all()
        );
    }

    public function test_a_hidden_block_is_not_rendered(): void
    {
        $page = Page::factory()->create();
        PageBlock::factory()->ofType(BlockType::RichText)->create(['page_id' => $page->id, 'is_visible' => false]);

        $this->assertCount(0, $page->visibleBlocks);
        $this->assertCount(1, $page->blocks);
    }

    public function test_all_twelve_types_have_a_view(): void
    {
        foreach (BlockType::cases() as $type) {
            $this->assertTrue(
                view()->exists($type->view()),
                'No view for block type '.$type->value
            );
        }
    }

    /**
     * The set is fixed and grows only by decision.
     *
     * DATA_MODEL section 8 said twelve and said a thirteenth needed a
     * decision rather than a commit. The thirteenth — committee_grid —
     * was added on 9 September 2026 on an explicit ruling, recorded as
     * Question 8 in docs/QUESTIONS.md and as a disclosed variation in
     * docs/OPEN_ITEMS.md.
     *
     * The number in this test is not the point. The point is that changing
     * it requires someone to come here, read this, and decide.
     */
    public function test_the_block_set_grows_only_by_a_recorded_decision(): void
    {
        $this->assertCount(13, BlockType::cases());

        $questions = file_get_contents(base_path('docs/QUESTIONS.md'));
        $this->assertStringContainsString('committee_grid', $questions);

        $model = file_get_contents(base_path('docs/DATA_MODEL.md'));
        $this->assertStringContainsString('committee_grid', $model);
    }

    /* The rules most likely to be lost ------------------------------------- */

    /**
     * Card geometry follows content. A grid that stretches every card to the
     * tallest sibling is the clearest tell of a generated interface, and
     * CLAUDE.md forbids it.
     */
    public function test_card_grids_size_to_their_own_content(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (['.b-cards', '.b-members', '.b-pillars__grid', '.b-figures__grid'] as $selector) {
            $rule = substr($css, strpos($css, $selector));
            $rule = substr($rule, 0, strpos($rule, '}'));

            $this->assertStringContainsString(
                'align-items: start',
                $rule,
                $selector.' stretches its cards to a common height.'
            );
        }
    }

    /**
     * Ruling 2. A grid of equal fractions gives every card the same width
     * whatever it holds. Where content genuinely varies, the layout has to
     * respond to it.
     */
    public function test_the_member_grid_gives_more_room_to_cards_that_earn_it(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);

        $spans = Country::with('authority.sectors')->get()
            ->map(fn ($country) => $country->cardSpan());

        $this->assertGreaterThan(1, $spans->unique()->count(), 'Every member card claims the same width.');
        $this->assertContains(1, $spans->all());
        $this->assertContains(2, $spans->all());
    }

    public function test_the_member_grid_uses_auto_fill_so_a_span_is_possible(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));
        $rule = substr($css, strpos($css, '.b-members {'));
        $rule = substr($rule, 0, strpos($rule, '}'));

        // auto-fit collapses empty tracks, which makes a span meaningless.
        $this->assertStringContainsString('auto-fill', $rule);
        $this->assertStringNotContainsString('auto-fit', $rule);
    }

    /**
     * The figure accent was --c-green on all four identically. An accent that
     * draws no distinction is decoration, which CLAUDE.md forbids.
     */
    public function test_the_key_figures_carry_no_decorative_accent(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));
        $rule = substr($css, strpos($css, '.b-figure {'));
        $rule = substr($rule, 0, strpos($rule, '}'));

        $this->assertStringNotContainsString('--c-green', $rule);
    }

    public function test_no_block_carries_a_scroll_entrance_animation(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertStringNotContainsString('@keyframes', $css);
        $this->assertStringNotContainsString('animation:', $css);
    }

    public function test_the_block_stylesheet_carries_no_hard_coded_colour(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(public_path('css/blocks.css')));

        $this->assertDoesNotMatchRegularExpression('/#[0-9A-Fa-f]{3,8}\b/', $css);
    }
}
