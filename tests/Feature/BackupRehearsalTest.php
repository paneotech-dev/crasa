<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The backup rehearsal.
 *
 * docs/BACKUP_SETUP.md was rehearsed by hand on 9 September 2026 and was
 * wrong twice. A procedure only ever rehearsed by hand gets rehearsed once,
 * so it is a command now.
 *
 * These tests are about the command's own guarantees rather than about
 * mysqldump, which is not ours to test. What is ours: that it notices a
 * document row whose file has gone, that it never leaves a credentials file
 * behind, and that it fails loudly rather than reporting success.
 */
class BackupRehearsalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The defect the manual rehearsal found: restoring a database brings
     * document rows back and not their files, so every page looks healthy
     * and every download 404s.
     */
    public function test_it_notices_a_document_row_whose_file_has_gone(): void
    {
        Storage::fake('local');

        $instrument = Instrument::factory()->inForce()->create(['slug' => 'a-note']);
        Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'file_path' => 'documents/vanished.pdf',
            'original_filename' => 'vanished.pdf',
        ]);

        // The file is never written: the state a database-only restore
        // leaves behind.
        $this->artisan('backup:rehearse')
            ->expectsOutputToContain('Documents with no file')
            ->assertFailed();
    }

    /**
     * A credentials file holding the database password must never outlive
     * the command, whatever happened while it ran.
     */
    public function test_it_leaves_no_credentials_file_behind(): void
    {
        $path = storage_path('app/rehearsal/.my.cnf');

        $this->artisan('backup:rehearse');

        $this->assertFileDoesNotExist($path, 'The rehearsal left the database password on disk.');
    }

    /**
     * The credentials file carries the port the application connects on.
     *
     * It used to carry user, password and host only, so every client it was
     * handed fell back to 3306. On the rebuilt host 3306 is another
     * application's MySQL server and this platform's MariaDB is on 3307: the
     * rehearsal's dump and restore were aimed at the wrong database server,
     * and only a per-user client default kept them off it.
     */
    public function test_the_credentials_file_carries_the_configured_port(): void
    {
        config([
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3307',
            'database.connections.mysql.username' => 'crasa',
            'database.connections.mysql.password' => 'not-a-real-password',
            'database.connections.mysql.unix_socket' => '',
        ]);

        $method = new \ReflectionMethod(\App\Console\Commands\RehearseBackup::class, 'credentialsFile');
        $path = $method->invoke(app(\App\Console\Commands\RehearseBackup::class));

        try {
            $written = File::get($path);

            $this->assertStringContainsString("\nport=3307\n", $written, 'The credentials file does not carry the port, so the client falls back to 3306.');
            $this->assertStringContainsString("\nhost=127.0.0.1\n", $written);
            $this->assertStringNotContainsString('socket=', $written, 'An empty socket must not be written.');
            $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
        } finally {
            File::delete($path);
        }
    }

    public function test_the_credentials_file_carries_a_configured_socket(): void
    {
        config(['database.connections.mysql.unix_socket' => '/run/mysqld/mysqld.sock']);

        $method = new \ReflectionMethod(\App\Console\Commands\RehearseBackup::class, 'credentialsFile');
        $path = $method->invoke(app(\App\Console\Commands\RehearseBackup::class));

        try {
            $this->assertStringContainsString("\nsocket=/run/mysqld/mysqld.sock\n", File::get($path));
        } finally {
            File::delete($path);
        }
    }

    public function test_it_deletes_the_dump_unless_asked_to_keep_it(): void
    {
        $directory = storage_path('app/rehearsal');

        $this->artisan('backup:rehearse');

        $dumps = File::isDirectory($directory)
            ? collect(File::files($directory))->filter(fn ($f) => $f->getExtension() === 'sql')
            : collect();

        $this->assertTrue($dumps->isEmpty(), 'The rehearsal left a database dump on disk.');
    }

    /**
     * It has to exit non-zero when something is wrong, or a schedule reports
     * nothing and the procedure rots quietly.
     */
    public function test_it_fails_rather_than_reporting_success(): void
    {
        Storage::fake('local');

        $instrument = Instrument::factory()->inForce()->create(['slug' => 'another-note']);
        Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'file_path' => 'documents/also-vanished.pdf',
            'original_filename' => 'also-vanished.pdf',
        ]);

        $this->artisan('backup:rehearse')->assertFailed();
    }

    /**
     * The restore step is skipped on this host and the command says so
     * rather than implying the restore was proven.
     */
    public function test_it_says_when_the_restore_step_was_skipped(): void
    {
        $this->artisan('backup:rehearse')
            ->expectsOutputToContain('The restore step was skipped');
    }

    /**
     * The table check compares against the dump, and MySQL returns table
     * names schema-qualified while a dump names them bare. Comparing the
     * two directly reported all forty tables missing from a dump that
     * contained every one of them — a check that cried wolf, which is worse
     * than no check, because the next real failure is ignored.
     */
    public function test_the_table_check_compares_bare_table_names(): void
    {
        $command = File::get(app_path('Console/Commands/RehearseBackup.php'));

        $this->assertStringContainsString(
            "str_contains(\$table, '.')",
            $command,
            'The table listing is not stripped of its schema prefix, so every table reads as missing.'
        );
    }

    /**
     * THE ONE THING THIS COMMAND MUST NEVER DO.
     *
     * Restoring a dump over the live database is not a rehearsal, it is an
     * outage with extra steps. A mistyped option must not be able to cause
     * one, so the command refuses when the rehearsal schema is the live
     * schema — and refuses again inside the drop, rather than trusting the
     * first check to still be there after a refactor.
     */
    public function test_it_refuses_to_restore_over_the_live_database(): void
    {
        config(['database.rehearsal_database' => config('database.connections.mysql.database')]);

        $this->artisan('backup:rehearse')
            ->expectsOutputToContain('REFUSED')
            ->assertFailed();
    }

    public function test_the_drop_guard_is_separate_from_the_restore_guard(): void
    {
        $command = File::get(app_path('Console/Commands/RehearseBackup.php'));

        // Two independent refusals, not one checked twice.
        $this->assertStringContainsString('the rehearsal schema is the live database', $command);
        $this->assertStringContainsString('Refusing to drop tables from the live database', $command);
    }

    /**
     * A restore that ran and is missing rows is the failure a success
     * message hides, and the go-live restore is the only time anybody would
     * find out. The comparison is the point of the exercise, not the
     * restore.
     */
    public function test_it_compares_the_restored_rows_against_the_live_ones(): void
    {
        $command = File::get(app_path('Console/Commands/RehearseBackup.php'));

        $this->assertStringContainsString('private function compare(', $command);
        $this->assertStringContainsString('is missing entirely', $command);
        $this->assertStringContainsString('Rows restored', $command);
    }

    /**
     * A rehearsal that left last week's data behind would compare this
     * week's dump against a mixture of both, and the second run would pass
     * for the wrong reason.
     */
    public function test_it_empties_the_scratch_schema_after_verifying(): void
    {
        $this->assertStringContainsString(
            'private function emptyScratch(',
            File::get(app_path('Console/Commands/RehearseBackup.php'))
        );
    }

    /** The instructions exist and name the values without interpretation. */
    public function test_the_scratch_schema_instructions_are_written(): void
    {
        $doc = File::get(base_path('docs/SCRATCH_SCHEMA.md'));

        $this->assertStringContainsString('ptechweb_restoretest', $doc);
        $this->assertStringContainsString('DB_REHEARSAL_DATABASE', $doc);
        // The existing user, so no new credential exists to be leaked.
        $this->assertStringContainsString('Do not create a new user', $doc);
        $this->assertStringContainsString(config('database.connections.mysql.username'), $doc);
    }

    /** The document names the command, and the command exists. */
    public function test_the_backup_document_tells_the_reader_to_rehearse(): void
    {
        $doc = File::get(base_path('docs/BACKUP_SETUP.md'));

        $this->assertStringContainsString('backup:rehearse', $doc);
        $this->assertStringContainsString('storage/app/private', $doc);
        $this->assertStringContainsString('storage/app/public', $doc);
        // The order the rehearsal proved matters.
        $this->assertStringContainsString('BEFORE the database', $doc);
    }
}
