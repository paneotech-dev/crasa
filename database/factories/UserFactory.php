<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Authority;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Tests only. Real accounts are created by the Secretariat at handover;
 * nothing here is seeded into a running environment.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(Str::random(32)),
            'remember_token' => Str::random(10),
            'country_id' => null,
            'authority_id' => null,
            'role' => Role::Viewer,
            'status' => UserStatus::Active,
            'preferred_locale' => 'en',
        ];
    }

    /** An administrator, enrolled in 2FA, which is what a working one is. */
    public function administrator(): static
    {
        return $this->state(fn () => [
            'role' => Role::Administrator,
            'country_id' => null,
            'two_factor_secret' => Str::random(32),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn () => ['role' => Role::Editor, 'country_id' => null]);
    }

    public function memberStateRep(?Country $country = null): static
    {
        return $this->state(fn () => [
            'role' => Role::MemberStateRep,
            'country_id' => $country?->getKey() ?? Country::factory(),
        ]);
    }

    public function committeeMember(?Country $country = null): static
    {
        return $this->state(fn () => [
            'role' => Role::CommitteeMember,
            'country_id' => $country?->getKey() ?? Country::factory(),
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn () => ['role' => Role::Moderator, 'country_id' => null]);
    }

    public function auditor(): static
    {
        return $this->state(fn () => ['role' => Role::Auditor, 'country_id' => null]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => ['role' => Role::Viewer]);
    }

    /** An administrator who has not completed enrolment. */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function forAuthority(Authority $authority): static
    {
        return $this->state(fn () => [
            'authority_id' => $authority->getKey(),
            'country_id' => $authority->country_id,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function invited(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Invited]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
