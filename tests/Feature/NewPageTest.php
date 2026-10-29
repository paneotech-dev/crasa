<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\Approvals;
use App\Support\PageAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F9: New page. */
class NewPageTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->editor = User::factory()->editor()->create();
    }

    public function test_the_button_and_form_are_there_for_editors_only(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/pages')->assertOk()->assertSee('New page');
        $this->actingAs($this->editor)->get('/en/admin/pages/new')->assertOk()->assertSee('Title in Français');
        $this->actingAs(User::factory()->moderator()->create())->get('/en/admin/pages/new')->assertForbidden();
        $this->actingAs(User::factory()->viewer()->create())->post('/en/admin/pages', ['en' => ['title' => 'x']])->assertForbidden();
    }

    public function test_creates_a_draft_standard_page_with_three_titles_and_a_menu_entry(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/pages', [
            'en' => ['title' => 'Spectrum harmonisation'],
            'fr' => ['title' => 'Harmonisation du spectre'],
            'pt' => ['title' => 'Harmonização do espectro'],
            'slug' => '',
            'menu' => 'footer',
        ])->assertRedirect('/en/admin/pages/spectrum-harmonisation');

        $page = Page::where('slug', 'spectrum-harmonisation')->firstOrFail();
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertSame('standard', $page->template);
        $this->assertSame('Harmonisation du spectre', $page->translated('title', '', 'fr'));
        $this->assertSame(1, $page->blocks()->count());

        $item = MenuItem::where('destination', 'spectrum-harmonisation')->firstOrFail();
        $this->assertSame('footer', $item->menu);
        $this->assertNull($item->url('en'), 'A draft page is not linked from the menu.');

        // Not on the public site, and editable with the block editor.
        $this->get('/en/spectrum-harmonisation')->assertNotFound();
        $this->actingAs($this->editor)->get('/en/admin/pages/spectrum-harmonisation')->assertOk()->assertSee('Send for approval');

        // Through approval to the site.
        $approval = Approvals::submit($page, $this->editor);
        Approvals::approve($approval, User::factory()->moderator()->create());
        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->assertNotNull($item->fresh()->url('en'));
    }

    public function test_address_rules(): void
    {
        Page::create(['slug' => 'taken', 'template' => 'standard', 'status' => PageStatus::Draft]);

        $this->actingAs($this->editor)->post('/en/admin/pages', ['en' => ['title' => 'A'], 'slug' => 'taken'])
            ->assertSessionHasErrors(['slug' => 'Another page already has this address. Choose a different one.']);
        $this->actingAs($this->editor)->post('/en/admin/pages', ['en' => ['title' => 'A'], 'slug' => 'publications'])
            ->assertSessionHasErrors('slug');
        $this->actingAs($this->editor)->post('/en/admin/pages', ['en' => ['title' => 'A'], 'slug' => 'admin'])
            ->assertSessionHasErrors('slug');
        $this->actingAs($this->editor)->post('/en/admin/pages', ['en' => ['title' => 'A'], 'slug' => 'Bad Address!'])
            ->assertSessionHasErrors('slug');
        $this->actingAs($this->editor)->post('/en/admin/pages', ['en' => ['title' => ''], 'slug' => 'fine'])
            ->assertSessionHasErrors(['en.title' => 'Title in English is required: it names the page in the menu and in search results.']);

        $this->assertTrue(PageAddress::isReserved('newsroom'));
        $this->assertSame('publications-2', PageAddress::suggest('Publications'));
    }
}
