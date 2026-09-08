<?php

namespace Tests\Feature;

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

class LocalisationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    public function test_the_root_redirects_to_a_language(): void
    {
        $this->get('/')->assertRedirect('http://localhost:8000/en');
    }

    public function test_the_root_honours_the_browser_preference(): void
    {
        $this->withHeader('Accept-Language', 'pt-PT,pt;q=0.9,en;q=0.5')
            ->get('/')
            ->assertRedirect('http://localhost:8000/pt');
    }

    public function test_the_root_honours_the_language_last_chosen(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->withHeader('Accept-Language', 'pt-PT,pt;q=0.9')
            ->get('/')
            ->assertRedirect('http://localhost:8000/fr');
    }

    public function test_each_supported_language_renders(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get('/'.$locale)
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'"', false);
        }
    }

    public function test_an_unsupported_language_is_not_found(): void
    {
        $this->get('/de')->assertNotFound();
    }

    public function test_the_interface_is_translated_not_only_the_url(): void
    {
        $this->get('/fr')->assertSee('Salle de presse');
        $this->get('/pt')->assertSee('Sala de imprensa');
        $this->get('/en')->assertSee('Newsroom');
    }

    public function test_the_switcher_offers_all_three_languages_and_marks_the_current_one(): void
    {
        $response = $this->get('/fr');

        $response->assertSee('href="http://localhost:8000/en"', false);
        $response->assertSee('href="http://localhost:8000/pt"', false);
        $response->assertSee('hreflang="fr"', false);
        $response->assertSee('aria-current="true"', false);
    }

    public function test_the_switcher_keeps_the_reader_on_the_same_page(): void
    {
        $request = Request::create('http://localhost:8000/fr/publications/roaming?page=2');

        $this->assertSame(
            'http://localhost:8000/pt/publications/roaming?page=2',
            Locales::urlFor('pt', $request)
        );
    }

    public function test_every_page_declares_its_alternates(): void
    {
        $response = $this->get('/en');

        foreach (Locales::SUPPORTED as $locale) {
            $response->assertSee('hreflang="'.$locale.'"', false);
        }
        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_the_chosen_language_is_remembered_for_the_root(): void
    {
        $this->get('/pt');

        $this->get('/')->assertRedirect('http://localhost:8000/pt');
    }
}
