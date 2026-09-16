<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * .env.example lists every environment key the application reads.
 *
 * The rebuild of 16 September found nine keys that were read but not listed
 * (docs/REBUILD.md, known defect 6), among them the database username. A
 * missing key is invisible until it matters: the config falls back to a
 * framework default, which for DB_USERNAME is `root`, and nothing complains
 * until a backup is aimed at the wrong account.
 *
 * A key counts as listed whether it is active or commented. The reference
 * block at the end of the file holds the framework's own keys commented out.
 */
class EnvExampleTest extends TestCase
{
    /** @return array<int, string> */
    private function keysRead(): array
    {
        $keys = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes'] as $directory) {
            /** @var SplFileInfo $file */
            foreach (File::allFiles(base_path($directory)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                // \s spans newlines, so a call split across lines still counts.
                preg_match_all('/\benv\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', $file->getContents(), $matches);
                array_push($keys, ...$matches[1]);
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /** @return array<int, string> */
    private function keysListed(): array
    {
        preg_match_all('/^#?\s*([A-Z][A-Z0-9_]*)=/m', File::get(base_path('.env.example')), $matches);

        return array_values(array_unique($matches[1]));
    }

    public function test_every_key_the_application_reads_is_listed(): void
    {
        $read = $this->keysRead();

        $this->assertNotEmpty($read, 'The scan found no env() calls at all, so it proved nothing.');

        $this->assertSame(
            [],
            array_values(array_diff($read, $this->keysListed())),
            'Read by the application but missing from .env.example. Add each one, with a safe placeholder.'
        );
    }

    /** The nine keys the rebuild found missing, named so a regression names them too. */
    public function test_the_keys_the_rebuild_found_missing_are_listed(): void
    {
        $listed = $this->keysListed();

        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_REHEARSAL_DATABASE',
            'SESSION_COOKIE', 'SESSION_SAME_SITE', 'SESSION_SECURE_COOKIE'] as $key) {
            $this->assertContains($key, $listed, "{$key} is read but not listed.");
        }
    }

    /** A copied template must never ship insecure cookies or a real credential. */
    public function test_the_template_is_safe_to_copy(): void
    {
        $example = File::get(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $example);
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $example, 'The template must not carry an application key.');
        $this->assertDoesNotMatchRegularExpression('/^#?\s*DB_USERNAME=root$/m', $example, 'root is not this platform\'s database user.');
        $this->assertDoesNotMatchRegularExpression('/^(DB_PASSWORD|MAIL_PASSWORD|AWS_SECRET_ACCESS_KEY)=(?!null$|$).+/m', $example, 'The template carries a secret.');
        $this->assertStringNotContainsString('hello@example.com', $example, 'The framework placeholder sender is rejected by most receiving servers.');
    }
}
