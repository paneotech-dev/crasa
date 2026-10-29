<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\User;
use App\Policies\PagePolicy;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Page and block editing, CM-01.
 *
 * The tests that matter most here are the ones about what an editor cannot
 * do. Server-side enforcement is a contractual requirement and a hidden
 * button is not enforcement, so every one of these hits the route directly.
 */
class PageEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $editor;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();

        $this->page = Page::create([
            'slug' => 'test-page', 'template' => 'standard',
            'status' => PageStatus::Draft, 'is_sample' => true,
        ]);
        $this->page->setTranslations('en', ['title' => 'Test page']);
    }

    private function block(BlockType $type = BlockType::RichText, int $position = 0): PageBlock
    {
        return PageBlock::create([
            'page_id' => $this->page->id,
            'block_type' => $type,
            'position' => $position,
            'is_visible' => true,
            'settings' => $type->defaultSettings(),
        ]);
    }

    /* Access ---------------------------------------------------------------- */

    public function test_a_viewer_reaches_no_part_of_content_editing(): void
    {
        $viewer = User::factory()->viewer()->create();
        $block = $this->block();

        $this->actingAs($viewer)->get('/en/admin/pages')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/pages/test-page')->assertForbidden();
        $this->actingAs($viewer)->put('/en/admin/pages/test-page', ['en' => ['title' => 'x']])->assertForbidden();
        $this->actingAs($viewer)->post('/en/admin/pages/test-page/blocks', ['block_type' => 'rich_text'])->assertForbidden();
        $this->actingAs($viewer)->delete("/en/admin/pages/test-page/blocks/{$block->id}")->assertForbidden();
    }

    public function test_an_editor_lists_and_opens_pages(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/pages')->assertOk()->assertSee('Test page');
        $this->actingAs($this->editor)->get('/en/admin/pages/test-page')->assertOk();
    }

    /* The workflow ---------------------------------------------------------- */

    public function test_the_workflow_moves_a_page_through_review(): void
    {
        $this->actingAs($this->editor)
            ->from('/en/admin/pages/test-page')
            ->put('/en/admin/pages/test-page/status', ['status' => 'review'])
            ->assertRedirect();

        $this->assertSame(PageStatus::Review, $this->page->fresh()->status);
    }

    /**
     * Publishing is checked separately from editing, on the server. Screen
     * 13's matrix happens to grant an editor both, so the account that
     * proves the check works is one the matrix denies: a member state
     * representative, who cannot manage content at all and is refused at
     * the first gate rather than the second.
     */
    public function test_publishing_is_refused_to_an_account_the_matrix_denies(): void
    {
        $representative = User::factory()->memberStateRep()->create();

        $this->actingAs($representative)
            ->put('/en/admin/pages/test-page/status', ['status' => 'review'])
            ->assertForbidden();

        $this->assertSame(PageStatus::Draft, $this->page->fresh()->status);
    }

    /**
     * Publishing asks a different question from editing.
     *
     * Screen 13's matrix happens to grant an editor both, so no role in
     * this build separates them and no route test can. The separation is
     * still real and it is asserted where it lives: the policy answers
     * update and publish from different permissions, so the day the matrix
     * changes, the controller already does the right thing.
     */
    public function test_the_policy_asks_a_different_permission_to_publish_than_to_edit(): void
    {
        $policy = new PagePolicy;

        $representative = User::factory()->memberStateRep()->create();

        $this->assertTrue($policy->update($this->editor, $this->page));
        // Day 2 (F2): pages need a moderator's approval by default, so an
        // editor edits but does not publish; the administrator still may.
        $this->assertFalse($policy->publish($this->editor, $this->page));
        $this->assertTrue($policy->publish($this->administrator, $this->page));

        $this->assertFalse($policy->update($representative, $this->page));
        $this->assertFalse($policy->publish($representative, $this->page));

        // And delete is a third permission again, which the matrix does
        // separate: an editor edits and publishes but does not delete.
        $this->assertFalse($policy->delete($this->editor, $this->page));
        $this->assertTrue($policy->delete($this->administrator, $this->page));
    }

    public function test_an_administrator_publishes_and_the_page_appears_on_the_site(): void
    {
        $this->get('/en/test-page')->assertNotFound();

        $this->actingAs($this->administrator)
            ->from('/en/admin/pages/test-page')
            ->put('/en/admin/pages/test-page/status', ['status' => 'published'])
            ->assertRedirect();

        $this->assertTrue($this->page->fresh()->isPublished());
        $this->assertNotNull($this->page->fresh()->published_at);
        $this->get('/en/test-page')->assertOk();
    }

    public function test_a_page_cannot_jump_to_a_state_that_does_not_follow(): void
    {
        // Draft to archived is not a transition: something has to have been
        // published before it can come off the site.
        $this->actingAs($this->administrator)
            ->put('/en/admin/pages/test-page/status', ['status' => 'archived'])
            ->assertStatus(422);

        $this->assertSame(PageStatus::Draft, $this->page->fresh()->status);
    }

    public function test_archiving_takes_a_page_off_the_public_site(): void
    {
        $this->page->update(['status' => PageStatus::Published, 'published_at' => now()]);
        $this->get('/en/test-page')->assertOk();

        $this->actingAs($this->administrator)
            ->from('/en/admin/pages/test-page')
            ->put('/en/admin/pages/test-page/status', ['status' => 'archived']);

        $this->get('/en/test-page')->assertNotFound();
    }

    /* Preview --------------------------------------------------------------- */

    public function test_preview_renders_an_unpublished_page_for_a_reviewer_and_nobody_else(): void
    {
        $this->block();

        $this->actingAs($this->editor)
            ->get('/en/admin/pages/test-page/preview')
            ->assertOk()
            ->assertSee('Test page');

        Auth::logout();

        $this->get('/en/admin/pages/test-page/preview')->assertRedirect();
        $this->get('/en/test-page')->assertNotFound();
    }

    /* Blocks ---------------------------------------------------------------- */

    public function test_a_new_block_arrives_hidden(): void
    {
        $this->page->update(['status' => PageStatus::Published, 'published_at' => now()]);

        $this->actingAs($this->editor)
            ->post('/en/admin/pages/test-page/blocks', ['block_type' => 'call_to_action'])
            ->assertRedirect();

        $block = $this->page->blocks()->sole();

        // Adding a block to a live page must not put an empty panel in
        // front of fourteen regulators before it has been filled in.
        $this->assertFalse($block->is_visible);
        $this->assertStringNotContainsString('b-cta__panel', $this->get('/en/test-page')->getContent());
    }

    public function test_settings_are_validated_on_the_server_whatever_the_form_sends(): void
    {
        $block = $this->block(BlockType::Pillars);

        // The rule says two to four columns.
        $this->actingAs($this->editor)
            ->from("/en/admin/pages/test-page/blocks/{$block->id}")
            ->put("/en/admin/pages/test-page/blocks/{$block->id}", ['settings' => ['columns' => 9]])
            ->assertSessionHasErrors('settings.columns');

        $this->assertSame(3, $block->fresh()->settings['columns']);
    }

    public function test_a_call_to_action_cannot_be_given_a_colour_that_is_not_in_the_palette(): void
    {
        $block = $this->block(BlockType::CallToAction);

        $this->actingAs($this->editor)
            ->from("/en/admin/pages/test-page/blocks/{$block->id}")
            ->put("/en/admin/pages/test-page/blocks/{$block->id}", ['settings' => ['tone' => 'indigo']])
            ->assertSessionHasErrors('settings.tone');
    }

    public function test_saving_a_block_writes_all_three_languages(): void
    {
        $block = $this->block();

        $this->actingAs($this->editor)
            ->put("/en/admin/pages/test-page/blocks/{$block->id}", [
                'settings' => ['alignment' => 'start'],
                'en' => ['heading' => 'Harmonisation', 'body' => 'One framework.'],
                'fr' => ['heading' => 'Harmonisation', 'body' => 'Un cadre.'],
                'pt' => ['heading' => 'Harmonizacao', 'body' => 'Um quadro.'],
            ])
            ->assertRedirect();

        $block->refresh()->load('translations');
        $this->assertSame('Un cadre.', $block->translate('body', 'fr')->value);
        $this->assertSame('Um quadro.', $block->translate('body', 'pt')->value);
    }

    public function test_moving_a_block_rewrites_the_whole_order_rather_than_swapping(): void
    {
        $first = $this->block(BlockType::RichText, 0);
        $second = $this->block(BlockType::CallToAction, 1);
        $third = $this->block(BlockType::KeyFigures, 2);

        $this->actingAs($this->editor)
            ->from('/en/admin/pages/test-page')
            ->put("/en/admin/pages/test-page/blocks/{$third->id}/move", ['direction' => 'up']);

        $this->assertSame(
            [$first->id, $third->id, $second->id],
            $this->page->blocks()->orderBy('position')->pluck('id')->all()
        );

        // And the positions are still 0, 1, 2 with no gaps and no ties.
        $this->assertSame([0, 1, 2], $this->page->blocks()->orderBy('position')->pluck('position')->all());
    }

    public function test_the_first_block_cannot_move_up_past_the_top(): void
    {
        $first = $this->block(BlockType::RichText, 0);
        $this->block(BlockType::CallToAction, 1);

        $this->actingAs($this->editor)
            ->from('/en/admin/pages/test-page')
            ->put("/en/admin/pages/test-page/blocks/{$first->id}/move", ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(0, $first->fresh()->position);
    }

    public function test_duplicating_a_block_carries_its_translations(): void
    {
        $block = $this->block();
        $block->setTranslations('en', ['heading' => 'Original', 'body' => 'English copy.']);
        $block->setTranslations('fr', ['heading' => 'Original', 'body' => 'Texte francais.']);
        $block->setTranslations('pt', ['heading' => 'Original', 'body' => 'Texto portugues.']);

        $this->actingAs($this->editor)
            ->post("/en/admin/pages/test-page/blocks/{$block->id}/duplicate")
            ->assertRedirect();

        $copy = $this->page->blocks()->where('id', '!=', $block->id)->sole();

        // A duplicate that loses the French and the Portuguese is not a
        // duplicate. It is a new block with an English draft in it.
        $this->assertSame('Texte francais.', $copy->translate('body', 'fr')->value);
        $this->assertSame('Texto portugues.', $copy->translate('body', 'pt')->value);
        $this->assertFalse($copy->is_visible);
        $this->assertSame($block->position + 1, $copy->position);
    }

    public function test_hiding_a_block_keeps_it_and_its_copy(): void
    {
        $block = $this->block();
        $block->setTranslations('en', ['heading' => 'Still here', 'body' => 'Kept.']);

        $this->actingAs($this->editor)
            ->from('/en/admin/pages/test-page')
            ->put("/en/admin/pages/test-page/blocks/{$block->id}/visibility");

        $this->assertFalse($block->fresh()->is_visible);
        $this->assertSame('Kept.', $block->fresh()->translate('body')->value);
        $this->assertDatabaseHas('page_blocks', ['id' => $block->id]);
    }

    public function test_deleting_a_block_needs_the_delete_permission_and_closes_the_gap(): void
    {
        $first = $this->block(BlockType::RichText, 0);
        $second = $this->block(BlockType::CallToAction, 1);
        $third = $this->block(BlockType::KeyFigures, 2);

        $this->actingAs($this->editor)
            ->delete("/en/admin/pages/test-page/blocks/{$second->id}")
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->delete("/en/admin/pages/test-page/blocks/{$second->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('page_blocks', ['id' => $second->id]);
        $this->assertSame([0, 1], $this->page->blocks()->orderBy('position')->pluck('position')->all());
        $this->assertSame([$first->id, $third->id], $this->page->blocks()->orderBy('position')->pluck('id')->all());
    }

    public function test_a_block_cannot_be_edited_through_a_page_it_does_not_belong_to(): void
    {
        $other = Page::create(['slug' => 'other-page', 'template' => 'standard', 'status' => PageStatus::Draft]);
        $block = $this->block();

        $this->actingAs($this->editor)
            ->get("/en/admin/pages/other-page/blocks/{$block->id}")
            ->assertNotFound();
    }

    public function test_the_block_form_is_generated_from_the_types_own_rules(): void
    {
        $block = $this->block(BlockType::Pillars);

        $this->actingAs($this->editor)
            ->get("/en/admin/pages/test-page/blocks/{$block->id}")
            ->assertOk()
            // columns is an integer rule, so it draws a number input
            ->assertSee('name="settings[columns]"', false)
            // items is an array of objects, so it draws a repeater
            ->assertSee('name="settings[items][0][icon]"', false);
    }

    public function test_the_repeaters_spare_row_is_not_saved_as_an_empty_item(): void
    {
        $block = $this->block(BlockType::KeyFigures);

        $this->actingAs($this->editor)
            ->put("/en/admin/pages/test-page/blocks/{$block->id}", [
                'settings' => ['items' => [['value' => '14'], ['value' => '']]],
            ])
            ->assertRedirect();

        $this->assertCount(1, $block->fresh()->settings['items']);
    }

    public function test_every_block_type_explains_itself_in_plain_language(): void
    {
        $html = $this->actingAs($this->editor)
            ->get('/en/admin/pages/test-page/blocks/add')
            ->assertOk()
            ->getContent();

        foreach (BlockType::cases() as $type) {
            $explanation = __('blocks.explain_'.$type->value);

            $this->assertNotSame('blocks.explain_'.$type->value, $explanation, $type->value.' has no explanation.');
            $this->assertStringContainsString(e($explanation), $html);
        }
    }
}
