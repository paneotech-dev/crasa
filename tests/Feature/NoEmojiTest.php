<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * No emoji, pictographic symbol or arrow glyph in any template or language
 * file. Where an icon is needed it comes from the shell-icon line set.
 */
class NoEmojiTest extends TestCase
{
    private const PATTERN = '/[\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F000}-\x{1FAFF}\x{FE0F}]/u';

    public function test_templates_and_language_files_carry_no_emoji(): void
    {
        $finder = (new Finder)->files()
            ->in([resource_path('views'), base_path('lang'), database_path('seeders'), app_path('Notifications')])
            ->name(['*.php', '*.json']);

        $hits = [];
        foreach ($finder as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match(self::PATTERN, $line)) {
                    $hits[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $hits, 'Emoji or pictographic characters found: '.implode(', ', $hits));
    }
}
