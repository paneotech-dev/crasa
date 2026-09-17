<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DeliveryCheck;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The two things that must be true before this platform carries real
 * regulatory data, and the check that stops one of them failing quietly.
 *
 * Both were left open by the security review of 9 September because both
 * need a decision from CRASA rather than a change to the code. What does not
 * need a decision is whether the platform tells anyone.
 */
class GoLiveBlockersTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    /* The mail check --------------------------------------------------------- */

    public function test_a_driver_that_delivers_nowhere_is_recognised_as_silent(): void
    {
        foreach (['log', 'array', 'null'] as $driver) {
            config(['mail.default' => $driver]);
            $this->assertTrue(DeliveryCheck::mailIsSilent(), $driver.' delivers nothing and is not being reported.');
        }

        config(['mail.default' => 'smtp']);
        $this->assertFalse(DeliveryCheck::mailIsSilent());
    }

    /**
     * The log driver is correct locally. That is the whole reason it exists,
     * and a warning that fires during development is a warning developers
     * learn to ignore before it ever reaches production.
     */
    public function test_the_log_driver_is_not_reported_in_local_development(): void
    {
        config(['mail.default' => 'log']);

        $this->app['env'] = 'local';
        $this->assertFalse(DeliveryCheck::mailIsSilentInProduction());

        $this->app['env'] = 'production';
        $this->assertTrue(DeliveryCheck::mailIsSilentInProduction());
    }

    public function test_the_frameworks_placeholder_sender_is_recognised(): void
    {
        config(['mail.from.address' => 'hello@example.com']);
        $this->assertTrue(DeliveryCheck::fromAddressIsUnset());

        config(['mail.from.address' => '']);
        $this->assertTrue(DeliveryCheck::fromAddressIsUnset());

        config(['mail.from.address' => 'no-reply@crasa.org']);
        $this->assertFalse(DeliveryCheck::fromAddressIsUnset());
    }

    /**
     * The banner is the part that actually gets seen. A log line on a shared
     * host is read by nobody; the Secretariat opens the back office daily.
     */
    public function test_the_back_office_says_so_on_every_screen_when_mail_goes_nowhere(): void
    {
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->assertSee('Mail is not being delivered.')
            // Named, so the reader knows what to change rather than only
            // that something is wrong.
            ->assertSee('log', false);
    }

    public function test_a_member_state_representative_is_not_shown_a_setting_they_cannot_change(): void
    {
        $this->seed(\Database\Seeders\CountrySeeder::class);
        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $rep = User::factory()->memberStateRep(\App\Models\Country::where('iso_code', 'bw')->sole())->create();

        $this->actingAs($rep)
            ->get('/en/submissions')
            ->assertOk()
            ->assertDontSee('Mail is not being delivered.');
    }

    public function test_the_back_office_says_nothing_when_mail_is_configured(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@crasa.org',
        ]);
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->assertDontSee('Mail is not being delivered.')
            ->assertDontSee('The sending address is still a placeholder.');
    }

    public function test_a_real_driver_with_a_placeholder_sender_is_still_flagged(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@example.com',
        ]);
        $this->app['env'] = 'production';

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->assertSee('The sending address is still a placeholder.');
    }

    /**
     * The check never becomes the thing that breaks the site. It is a
     * report, not a guard: a platform that refused to serve pages because
     * mail was misconfigured would be a worse outcome than one that
     * delivers no mail.
     */
    public function test_the_check_never_blocks_a_request(): void
    {
        // Seeded before the environment is switched: Laravel asks for
        // confirmation before seeding in production, and the prompt has
        // nothing to answer it in a test.
        $this->seedComposedPages();

        config(['mail.default' => 'log']);
        $this->app['env'] = 'production';

        $this->get('/en')->assertOk();
        $this->get('/en/publications')->assertOk();
    }

    /* The documents ---------------------------------------------------------- */

    /**
     * Every artisan command a document tells the operator to run must exist.
     *
     * docs/DEMO_SCRIPT.md and the demonstration seeder both told the
     * operator to run `user:create`, and the command was called
     * `crasa:user`. On the day of the workshop the instruction for creating
     * the four demonstration accounts would have failed at the prompt, in
     * front of fourteen regulators.
     */
    public function test_every_command_the_documents_name_actually_exists(): void
    {
        $registered = array_keys($this->app[Kernel::class]->all());

        $documents = glob(base_path('docs/*.md'));
        $this->assertNotEmpty($documents);

        /*
         * Commands a document names as arriving with a package it tells the
         * reader to install. Listed explicitly rather than pattern-matched,
         * so adding one is a decision: the point of this test is that an
         * instruction naming a command nobody can run is a broken
         * instruction, and "it will exist later" has to be stated.
         */
        $afterInstallation = [
            // spatie/laravel-backup, per docs/BACKUP_SETUP.md.
            'backup:list',
            'backup:run',
        ];

        $missing = [];

        foreach ($documents as $path) {
            preg_match_all('~php artisan ([a-z][a-z0-9:_-]+)~', file_get_contents($path), $matches);

            foreach (array_unique($matches[1]) as $command) {
                if (! in_array($command, $registered, true) && ! in_array($command, $afterInstallation, true)) {
                    $missing[$command] = basename($path);
                }
            }
        }

        $this->assertSame([], $missing, 'Documents name commands that do not exist: '.json_encode($missing));
    }

    /**
     * Both documents go to CRASA and both describe this server. A procedure
     * that names the wrong PHP binary or the wrong database is a procedure
     * that fails at the moment it is needed, which is during a restore.
     */
    public function test_the_setup_documents_describe_this_server_and_not_a_generic_one(): void
    {
        foreach (['docs/BACKUP_SETUP.md', 'docs/MAIL_SETUP.md'] as $path) {
            $this->assertFileExists(base_path($path));
            $doc = file_get_contents(base_path($path));

            // The PHP path is not optional: the default php on this account
            // is 8.2 and this application requires 8.3.
            $this->assertStringContainsString(
                '/opt/cpanel/ea-php83/root/usr/bin/php',
                $doc,
                basename($path).' gives a cron line that would run under the wrong PHP.'
            );
            $this->assertStringContainsString('/home/ptechweb/crasadev/artisan', $doc);
        }
    }

    public function test_the_backup_document_restores_the_database_it_dumps(): void
    {
        $doc = file_get_contents(base_path('docs/BACKUP_SETUP.md'));

        // The restore line and the dump filename have to name the same
        // database. A procedure that restores one database from another's
        // dump fails at the only moment it is ever used.
        preg_match('~mysql -u (\S+) -p (\S+) < (\S+)~', $doc, $restore);

        $this->assertNotEmpty($restore, 'The backup document has no restore command.');
        $this->assertStringContainsString(
            $restore[2],
            $restore[3],
            'The restore command loads a dump of a different database from the one it restores into.'
        );

        // Commands quoted in a restore procedure have to exist.
        foreach (['images:derivatives'] as $command) {
            $this->assertStringContainsString($command, $doc);
            $this->assertArrayHasKey($command, $this->app[Kernel::class]->all());
        }
    }

    /**
     * A restore procedure that is not tested is a hypothesis. The document
     * has to say how to prove it, not only how to run it.
     */
    public function test_the_backup_document_carries_a_restore_verification(): void
    {
        $doc = file_get_contents(base_path('docs/BACKUP_SETUP.md'));

        $this->assertStringContainsStringIgnoringCase('restore', $doc);
        $this->assertStringContainsStringIgnoringCase('audit_logs', $doc);
        // The download check is the only one that proves the database and
        // the file store were restored consistently with each other.
        $this->assertStringContainsStringIgnoringCase('download', $doc);
        // And the alternative, for a client who cannot provision S3.
        $this->assertStringContainsStringIgnoringCase('cannot provision S3', $doc);
    }

    public function test_the_mail_document_covers_both_routes_and_how_to_verify_delivery(): void
    {
        $doc = file_get_contents(base_path('docs/MAIL_SETUP.md'));

        $this->assertStringContainsStringIgnoringCase('cPanel SMTP', $doc);
        $this->assertStringContainsStringIgnoringCase('transactional mail service', $doc);

        // Sent is not delivered. SPF, DKIM and DMARC are the difference
        // between the two for fourteen government recipients.
        foreach (['SPF', 'DKIM', 'DMARC'] as $record) {
            $this->assertStringContainsString($record, $doc, 'The mail document does not mention '.$record.'.');
        }
    }

    /**
     * The two documents have to point at each other. Backups report their
     * own failure by email, so a backup schedule set up while mail is silent
     * has no way of telling anyone it has stopped.
     */
    public function test_each_document_names_the_dependency_on_the_other(): void
    {
        $this->assertStringContainsString(
            'docs/MAIL_SETUP.md',
            file_get_contents(base_path('docs/BACKUP_SETUP.md'))
        );
        $this->assertStringContainsString(
            'docs/BACKUP_SETUP.md',
            file_get_contents(base_path('docs/MAIL_SETUP.md'))
        );
    }
}
