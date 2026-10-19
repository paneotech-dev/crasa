<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * A workshop room signs in from one address.
 *
 * Until 14 October 2026 the sign-in route allowed 20 attempts per IP per
 * ten minutes, successful or not: the 21st delegate on the venue Wi-Fi got a
 * bare English "429 Too Many Requests". Now only failures count against the
 * address (200 per ten minutes since 19 October; 30 before), and the raw cap
 * is 600.
 */
class WorkshopSignInTest extends TestCase
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

    public function test_forty_delegates_on_one_address_all_sign_in(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $user = User::factory()->viewer()->create(['password' => Hash::make('correct-horse-42')]);
            $this->post('/en/login', ['email' => $user->email, 'password' => 'correct-horse-42'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($user);
            $this->post('/en/logout');
        }
    }

    public function test_two_hundred_failures_from_one_address_pause_it_in_the_readers_language(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->post('/fr/login', ['email' => "nobody{$i}@example.test", 'password' => 'wrong']);
        }

        $user = User::factory()->viewer()->create(['password' => Hash::make('correct-horse-42')]);
        $this->from('/fr/login')->post('/fr/login', ['email' => $user->email, 'password' => 'correct-horse-42'])
            ->assertRedirect('/fr/login');
        $this->assertGuest();
        $this->get('/fr/login')->assertSee('Trop de tentatives');
    }

    public function test_the_429_page_is_branded_and_translated(): void
    {
        config(['app.debug' => false]);
        Route::get('/pt/__busy', fn () => throw new HttpException(429));

        $this->get('/pt/__busy')->assertStatus(429)->assertSee('Demasiadas tentativas')->assertSee('Voltar à página inicial');
    }
}
