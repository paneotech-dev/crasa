<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The translations screen names .env settings only to those who may manage
 * settings. An editor reads a plain sentence; with a service configured,
 * nobody reads anything about configuration.
 */
class TranslationServiceNoticeByRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        config(['crasa.two_factor_required' => false, 'machine_translation.driver' => 'none']);
    }

    public function test_an_editor_sees_a_plain_sentence_without_setup_wording(): void
    {
        $editor = User::factory()->editor()->create();

        $plain = [
            'en' => 'Automatic translation drafts are not available yet. Translations are written by hand meanwhile.',
            'fr' => 'Les brouillons de traduction automatique ne sont pas encore disponibles.',
            'pt' => 'Os rascunhos de tradução automática ainda não estão disponíveis.',
        ];

        foreach ($plain as $locale => $sentence) {
            $this->actingAs($editor)->get("/{$locale}/admin/translations")->assertOk()
                ->assertSee('data-mt-unconfigured', false)
                ->assertSee($sentence)
                ->assertDontSee('CRASA_MT_DRIVER')
                ->assertDontSee('.env')
                ->assertDontSee('API_KEY');
        }

        config(['machine_translation.driver' => 'rafiki', 'machine_translation.rafiki.key' => null]);
        $this->actingAs($editor)->get('/en/admin/translations')->assertOk()
            ->assertDontSee('RAFIKI_API_KEY')
            ->assertDontSee('Missing settings');
    }

    public function test_an_administrator_still_sees_the_setup_wording(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/en/admin/translations')->assertOk()
            ->assertSee('No machine translation service is configured')
            ->assertSee('CRASA_MT_DRIVER')
            ->assertDontSee('Automatic translation drafts are not available yet.');
    }

    public function test_with_a_service_configured_nobody_sees_a_configuration_notice(): void
    {
        config(['machine_translation.driver' => 'rafiki', 'machine_translation.rafiki.key' => 'sk-test-key']);

        foreach ([User::factory()->editor()->create(), User::factory()->administrator()->create()] as $user) {
            $this->actingAs($user)->get('/en/admin/translations')->assertOk()
                ->assertDontSee('data-mt-unconfigured', false)
                ->assertDontSee('CRASA_MT_DRIVER')
                ->assertDontSee('Automatic translation drafts are not available yet.');
        }
    }
}
