<?php

namespace App\Services\MachineTranslation;

/**
 * A machine translation service. Each driver translates one string from one
 * supported locale (en, fr, pt) to another and returns null when it cannot,
 * so a failure leaves the field untranslated rather than wrong.
 */
interface Translator
{
    /** The driver name, as written in the audit log. */
    public function name(): string;

    /** Whether the driver is configured well enough to be called. */
    public function available(): bool;

    public function translate(string $text, string $from, string $to, bool $html = false): ?string;
}
