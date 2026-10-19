<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteObject;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A workshop room on one Wi-Fi (19 October 2026).
 *
 * Every anonymous throttle:N,M limit used to share one counter per address
 * for signed-out readers, so six mistyped passwords in the room blocked
 * password resets for an hour, and a few registrations in one language
 * blocked the form in the next. Each path now has a named limiter of its
 * own, and sign-in failures are counted per account.
 */
class RateLimitSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-42-ok';

    private function onNetwork(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_failed_sign_ins_never_spend_the_password_reset_allowance(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->onNetwork('10.1.1.1')->post('/en/login', ['email' => "typo{$i}@example.test", 'password' => 'wrong']);
        }

        // Then the same room asks for resets and sets passwords: never a 429.
        for ($i = 0; $i < 10; $i++) {
            $this->onNetwork('10.1.1.1')->post('/fr/password/forgotten', ['email' => "person{$i}@example.test"])
                ->assertRedirect();
        }

        $this->onNetwork('10.1.1.1')->post('/en/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'person0@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertSessionHasErrors('email');
    }

    public function test_password_resets_never_spend_the_sign_in_allowance(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->onNetwork('10.1.1.2')->post('/en/password/forgotten', ['email' => "person{$i}@example.test"]);
        }

        $user = User::factory()->viewer()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->onNetwork('10.1.1.2')->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_failures_are_counted_per_account_whatever_the_network(): void
    {
        $alice = User::factory()->viewer()->create(['password' => Hash::make(self::PASSWORD)]);
        $bob = User::factory()->viewer()->create(['password' => Hash::make(self::PASSWORD)]);

        // Ten failures on Alice, spread over five networks.
        for ($i = 0; $i < 10; $i++) {
            $this->onNetwork('10.2.0.'.($i % 5))->post('/en/login', ['email' => $alice->email, 'password' => 'wrong']);
        }

        // Alice is locked, even from a sixth network with the right password.
        $this->onNetwork('10.2.0.99')->post('/en/login', ['email' => $alice->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Bob, in the same room, is not affected.
        $this->onNetwork('10.2.0.1')->post('/en/login', ['email' => $bob->email, 'password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($bob);
    }

    public function test_a_hundred_people_mistyping_once_do_not_pause_the_room(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->onNetwork('10.3.3.3')->post('/en/login', ['email' => "delegate{$i}@example.test", 'password' => 'typo']);
        }

        $user = User::factory()->viewer()->create(['password' => Hash::make(self::PASSWORD)]);

        $this->onNetwork('10.3.3.3')->post('/en/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_one_address_cannot_be_flooded_with_reset_mails(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->onNetwork('10.4.4.4')->post('/en/password/forgotten', ['email' => 'target@example.test'])->assertRedirect();
        }

        $this->onNetwork('10.4.4.5')->post('/en/password/forgotten', ['email' => 'target@example.test'])->assertStatus(429);

        // Another person's request from the same room is unaffected.
        $this->onNetwork('10.4.4.4')->post('/en/password/forgotten', ['email' => 'someone.else@example.test'])->assertRedirect();
    }

    public function test_every_throttled_application_route_uses_a_named_limiter(): void
    {
        $anonymous = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteObject $route) => ! str_starts_with((string) $route->getActionName(), 'Illuminate\\'))
            ->flatMap(fn (RouteObject $route) => collect($route->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && preg_match('/^throttle:\d/', $m))
                ->map(fn ($m) => ($route->getName() ?? $route->uri()).' '.$m))
            ->values()
            ->all();

        $this->assertSame([], $anonymous, 'Anonymous limits share one counter per address: '.implode(', ', $anonymous));
    }
}
