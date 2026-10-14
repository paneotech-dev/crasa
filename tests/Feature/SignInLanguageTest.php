<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Signing in, in the reader's language, with a way to see the password.
 *
 * Three sign-ins failed in the presentation of 14 October 2026 on a
 * password nobody could see. And until that day every validation message
 * on a French or Portuguese page was in English: there was no
 * lang/fr/validation.php or lang/pt/validation.php.
 */
class SignInLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function languages(): array
    {
        return [
            'en' => ['en', 'do not match an active account', 'must be a valid email address', 'Show password'],
            'fr' => ['fr', 'ne correspondent à aucun compte actif', 'doit être une adresse électronique valide', 'Afficher le mot de passe'],
            'pt' => ['pt', 'não correspondem a nenhuma conta ativa', 'tem de ser um endereço de correio eletrónico válido', 'Mostrar a palavra-passe'],
        ];
    }

    #[DataProvider('languages')]
    public function test_a_wrong_password_is_explained_in_the_pages_language(string $locale, string $wrong, string $invalid, string $show): void
    {
        $user = User::factory()->viewer()->create();

        $this->from("/{$locale}/login")->post("/{$locale}/login", ['email' => $user->email, 'password' => 'not-the-password'])
            ->assertRedirect("/{$locale}/login");
        $this->get("/{$locale}/login")->assertSee($wrong)->assertSee($user->email);

        $this->from("/{$locale}/login")->post("/{$locale}/login", ['email' => 'not-an-address', 'password' => 'x']);
        $this->get("/{$locale}/login")->assertSee($invalid);
    }

    #[DataProvider('languages')]
    public function test_the_password_field_has_a_show_password_control_in_the_pages_language(string $locale, string $wrong, string $invalid, string $show): void
    {
        $html = $this->get("/{$locale}/login")->assertOk()->getContent();

        $this->assertStringContainsString('js/password.js', $html);
        $this->assertStringContainsString('<meta name="crasa-pw-show" content="'.e($show).'">', $html);
        // Without script it stays a plain password field.
        $this->assertStringContainsString('type="password" id="password"', $html);
    }

    public function test_the_show_password_script_exists_and_never_submits_plain_text(): void
    {
        $js = file_get_contents(public_path('js/password.js'));

        $this->assertStringContainsString("input.type = 'password'", $js);
        $this->assertStringContainsString("addEventListener('submit'", $js);
    }
}
