<?php

namespace App\Support;

/**
 * A translatable field after resolution: the text, the language it is
 * actually written in, and whether that language is the one asked for.
 */
final class ResolvedText
{
    public function __construct(
        public readonly string $value,
        public readonly string $locale,
        public readonly string $requestedLocale,
    ) {}

    /**
     * True when the reader asked for one language and is being shown another.
     * Views use this to render the inline fallback notice and to set a lang
     * attribute on the element, so screen readers switch voice correctly.
     */
    public function isFallback(): bool
    {
        return $this->locale !== $this->requestedLocale;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
