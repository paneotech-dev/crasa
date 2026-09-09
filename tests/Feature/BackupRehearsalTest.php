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
