<?php

namespace Tests\Feature;

use App\Console\Commands\DemoAccounts;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** F6: the demonstration accounts command. */
class DemoAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        if (class_exists(AuthoritySeeder::class)) {
            $this->seed(AuthoritySeeder::class);
        }
        $this->file = tempnam(sys_get_temp_dir(), 'creds');
        file_put_contents($this->file, "Existing line\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_it_creates_the_missing_accounts_appends_passwords_and_never_prints_them(): void
    {
        $existing = User::factory()->editor()->create(['email' => 'demo.editor@crasadev.invalid', 'password' => 'kept-as-it-was-123']);
        $hash = $existing->fresh()->password;

        $this->artisan('crasa:demo-accounts', ['--file' => $this->file])->assertSuccessful();

        $contents = file_get_contents($this->file);
        $this->assertStringStartsWith("Existing line\n", $contents);
        $this->assertStringContainsString('Day 2 accounts', $contents);

        // The existing account is untouched and not in the file.
        $this->assertSame($hash, $existing->fresh()->password);
        $this->assertStringNotContainsString('demo.editor@crasadev.invalid ', $contents);

        preg_match('/^demo\.viewer2@crasadev\.invalid\s+Viewer\s+((?:[A-Z][a-z]+){4}\d\d)$/m', $contents, $m);
        $this->assertNotEmpty($m, 'four-word password line for demo.viewer2');
        $viewer2 = User::firstWhere('email', 'demo.viewer2@crasadev.invalid');
        $this->assertTrue(Hash::check($m[1], $viewer2->password));
        $this->assertSame(Role::Viewer, $viewer2->role);
        $this->assertNull($viewer2->two_factor_confirmed_at);

        $committee2 = User::firstWhere('email', 'demo.committee2@crasadev.invalid');
        $this->assertSame(['postal-and-logistics'], $committee2->committees()->pluck('slug')->all());
    }

    public function test_the_output_lists_emails_and_roles_but_no_password(): void
    {
        \Illuminate\Support\Facades\Artisan::call('crasa:demo-accounts', ['--file' => $this->file]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $contents = file_get_contents($this->file);

        preg_match_all('/\s((?:[A-Z][a-z]+){4}\d\d)$/m', $contents, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $password) {
            $this->assertStringNotContainsString($password, $output);
        }
        $this->assertStringContainsString('demo.viewer2@crasadev.invalid', $output);
    }

    public function test_a_second_run_creates_nothing_and_roles_this_build_lacks_are_skipped(): void
    {
        $this->artisan('crasa:demo-accounts', ['--file' => $this->file])->assertSuccessful();
        $count = User::count();
        $size = filesize($this->file);

        $this->artisan('crasa:demo-accounts', ['--file' => $this->file])->assertSuccessful();

        clearstatcache();
        $this->assertSame($count, User::count());
        $this->assertSame($size, filesize($this->file));

        foreach (DemoAccounts::ACCOUNTS as [$email, $role]) {
            $this->assertSame(Role::tryFrom($role) !== null, User::where('email', $email)->exists(), $email);
        }
    }
}
