<?php

namespace App\Services\MachineTranslation;

/**
 * No service configured. Translates nothing and calls nothing.
 */
final class NoTranslator implements Translator
{
    public function name(): string
    {
        return 'none';
    }

    public function available(): bool
    {
        return false;
    }

    public function translate(string $text, string $from, string $to, bool $html = false): ?string
    {
        return null;
    }
}
