<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Every interface string exists in French and Portuguese.
 *
 * A key in lang/en that is missing from lang/fr or lang/pt renders in English
 * on the translated site with no notice, so it is caught here instead of by a
 * reader. The allow list in tests/Fixtures/lang-identical-allowed.json names
 * the few keys that are proper nouns or codes (an address, a citation format);
 * "missing" there excuses a key from existing, "keys" excuses a value from
 * being identical to the English in the static audit.
 *
 * Plain PHPUnit, no application boot: the files are read as arrays.
 */
class LangCompletenessTest extends TestCase
{
    private const LOCALES = ['fr', 'pt'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, string> */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += self::flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function load(string $locale): array
    {
        $out = [];
        foreach (glob(self::root()."/lang/{$locale}/*.php") as $file) {
            foreach (self::flatten(require $file) as $key => $value) {
                $out[basename($file, '.php').'.'.$key] = $value;
            }
        }

        return $out;
    }

    private static function allowed(string $list): array
    {
        $file = self::root().'/tests/Fixtures/lang-identical-allowed.json';

        return json_decode((string) file_get_contents($file), true)[$list] ?? [];
    }

    public function test_every_english_key_exists_in_french_and_portuguese(): void
    {
        $english = self::load('en');
        $allowed = self::allowed('missing');

        foreach (self::LOCALES as $locale) {
            $translated = self::load($locale);
            $missing = array_values(array_diff(array_keys(array_diff_key($english, $translated)), $allowed));
            $this->assertSame([], $missing, "Keys in lang/en missing from lang/{$locale}: ".implode(', ', $missing));
        }
    }

    public function test_placeholders_match_the_english(): void
    {
        $english = self::load('en');

        foreach (self::LOCALES as $locale) {
            $wrong = [];
            foreach (self::load($locale) as $key => $value) {
                if (! isset($english[$key])) {
                    continue;
                }
                preg_match_all('/:([a-z_]+)/', $english[$key], $a);
                preg_match_all('/:([a-z_]+)/', $value, $b);
                $a = array_unique($a[1]);
                $b = array_unique($b[1]);
                sort($a);
                sort($b);
                if ($a !== $b) {
                    $wrong[] = $key;
                }
            }
            $this->assertSame([], $wrong, "Placeholders in lang/{$locale} differ from the English: ".implode(', ', $wrong));
        }
    }

    /**
     * Words that are never correct without their accent. A French or
     * Portuguese string typed without accents reads as unfinished.
     */
    public function test_no_string_is_written_without_its_accents(): void
    {
        $patterns = [
            'fr' => '/\b(etat|etats|ete|deja|tres|apres|etre|donnees|secretariat|regulateurs?|reglementaire|decisions?|declarations?|deposees?|envoyes?|envoyees?|enregistrees?|creees?|validees?|modifiees?|publiees?|francais|periodes?|reference|categories?|annee|securite)\b/u',
            'pt' => '/\b(\w+cao|\w+coes|nao|sao|estao|tambem|ja|pagina|paginas|ultimo|ultima|proxima|proximo|historico|periodo|titulo|codigo|relatorio|obrigatorio|Notificacoes)\b/u',
        ];

        foreach (self::LOCALES as $locale) {
            $hits = [];
            foreach (self::load($locale) as $key => $value) {
                $text = preg_replace('/:[a-z_]+|https?:\S+|<[^>]+>|[a-z0-9-]+-[a-z0-9-]+/', ' ', $value);
                if (preg_match($patterns[$locale].'i', $text, $m)) {
                    $hits[] = "{$key} ({$m[0]})";
                }
            }
            $this->assertSame([], $hits, "Unaccented words in lang/{$locale}: ".implode(', ', $hits));
        }
    }
}
