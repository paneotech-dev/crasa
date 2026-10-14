<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The error pages: in the reader's language, inside the site, with a way home.
 *
 * Until 14 October 2026 a refused page was Laravel's default "403
 * Forbidden" in English with no link anywhere, and an expired form was
 * "Page Expired". The member account met the first in front of the
 * regulators.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        config(['app.debug' => false]);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function languages(): array
    {
        return [
            'en' => ['en', 'Page not found', 'Back to the home page'],
            'fr' => ['fr', 'Page introuvable', 'Retour à la page d\'accueil'],
            'pt' => ['pt', 'Página não encontrada', 'Voltar à página inicial'],
        ];
    }

    #[DataProvider('languages')]
    public function test_a_missing_page_is_in_the_readers_language_with_the_site_around_it(string $locale, string $title, string $home): void
    {
        $html = $this->get("/{$locale}/no-such-page-here")->assertNotFound()->getContent();

        $this->assertStringContainsString('<html lang="'.$locale.'"', $html);
        $this->assertStringContainsString(e($title), $html);
        $this->assertStringContainsString(e($home), $html);
        $this->assertStringContainsString('href="'.url('/'.$locale).'"', $html);
        // The site's own header, so the menu is there.
        $this->assertStringContainsString('c-skip', $html);
        $this->assertStringNotContainsString('Not Found</title>', $html);
    }

    public function test_an_address_without_a_language_falls_back_to_english(): void
    {
        $this->get('/nothing-at-all')->assertNotFound()->assertSee('Page not found');
    }

    public function test_a_refused_page_explains_itself_in_the_readers_language(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/fr/admin/users')->assertForbidden()
            ->assertSee('Cette page n&#039;est pas ouverte à votre compte', false)
            ->assertSee('Retour à la page d&#039;accueil', false);
    }

    public function test_an_expired_form_goes_back_to_the_form_with_a_notice_and_the_input(): void
    {
        Route::middleware('web')->post('/en/__expired-form', fn () => throw new TokenMismatchException);

        $this->from('/en/contact')
            ->post('/en/__expired-form', ['name' => 'Ana', 'password' => 'secret-word'])
            ->assertRedirect('/en/contact')
            ->assertSessionHas('expired', 'Your session expired, please try again. Check the form and send it again.')
            ->assertSessionHasInput('name', 'Ana')
            ->assertSessionMissing('_old_input.password');

        $this->get('/en/contact')->assertSee('Your session expired, please try again.');
    }

    public function test_an_expired_form_from_elsewhere_goes_home(): void
    {
        Route::middleware('web')->post('/pt/__expired-form', fn () => throw new TokenMismatchException);

        $this->post('/pt/__expired-form', [], ['referer' => 'https://example.org/'])->assertRedirect(url('/pt'));
    }

    public function test_a_server_error_page_needs_nothing_from_the_database(): void
    {
        Route::get('/fr/__boom', fn () => throw new \RuntimeException('boom'));

        $this->get('/fr/__boom')->assertStatus(500)
            ->assertSee('Un problème est survenu de notre côté')
            ->assertDontSee('boom');
    }
}
