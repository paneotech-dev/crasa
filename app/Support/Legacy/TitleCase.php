<?php

namespace App\Support\Legacy;

use Illuminate\Support\Str;

/**
 * Legacy titles, re-cased to the house voice.
 *
 * WHY THIS IS NOT OPTIONAL. Most of the legacy site's headlines are set in
 * capitals: "CRASA CHAIRPERSON PAYS COURTESY VISIT TO BOCRA". CLAUDE.md's
 * first writing rule is sentence case everywhere, and importing thirty
 * shouted headlines would put the rule's clearest violation on the
 * newsroom's front page on the day of the workshop.
 *
 * WHY IT ONLY TOUCHES SHOUTED TITLES. A title that is already mixed case is
 * the author's own choice and is left exactly as written. Only a title that
 * is more than two-thirds capitals is re-cased, because that is a
 * typographic decision rather than an editorial one.
 *
 * THE ACRONYMS ARE LISTED, NOT GUESSED. Lower-casing CRASA, SADC or ICASA
 * would be worse than shouting. Every acronym below appears in the legacy
 * corpus; anything not listed is left in sentence case and the record is
 * flagged, so a person reads it rather than a heuristic deciding.
 */
class TitleCase
{
    /**
     * Acronyms and proper forms that survive re-casing.
     *
     * @var list<string>
     */
    public const KEEP = [
        'CRASA', 'SADC', 'COMESA', 'ITU', 'BDT', 'UPU', 'PAPU', 'PUASP', 'BEREC', 'EACO', 'ARICEA',
        'ICT', 'ICTs', 'AGM', 'EXCO', 'HRDC', 'FAC', 'EOI', 'DFS', 'HR', 'AI', 'MoU', 'MOU',
        'QoS', 'QoE', 'IMT', 'PPDR', 'RFSAP', 'SRD', 'SRDs', 'PMR', 'VHF', 'UHF', 'USF', 'UA',
        'BOCRA', 'MACRA', 'ICASA', 'CRAN', 'POTRAZ', 'TCRA', 'ZICTA', 'ESCCOM', 'INCM', 'INACOM',
        'ARPTC', 'LCA', 'ICTA', 'COVID', 'COVID-19', '5G', 'G5', 'USA', 'EU', 'AU', 'GDP',
    ];

    /**
     * Proper nouns that take a capital wherever they appear.
     *
     * Sentence case lower-cases everything but the first word, which turns
     * "LILONGWE, MALAWI, 16th to 17th APRIL" into a sentence about nowhere
     * in no month. The fourteen member states are here because they are the
     * platform's own vocabulary; the cities and months are here because
     * they appear in the legacy corpus's meeting titles.
     *
     * @var list<string>
     */
    public const PROPER = [
        'Angola', 'Botswana', 'Comoros', 'Congo', 'Eswatini', 'Lesotho', 'Malawi',
        'Mauritius', 'Mozambique', 'Namibia', 'Tanzania', 'Zambia', 'Zimbabwe',
        'Africa', 'African', 'Southern', 'America', 'Arab', 'Washington', 'States',
        'Gaborone', 'Lilongwe', 'Maun', 'Kinshasa', 'Dar', 'Salaam', 'Es',
        'Windhoek', 'Maseru', 'Harare', 'Lusaka', 'Pretoria', 'Johannesburg', 'Victoria',
        'January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December',
        'Chairperson', 'Secretariat', 'Executive', 'Secretary', 'Dr', 'Mr', 'Mrs', 'Ms',
        'Kingdom', 'Republic', 'School', 'International', 'Westwood', 'Huawei',
        'Cullen', 'Stanley', 'Shanapinda', 'Girls', 'Day', 'Post', 'World',
    ];
    public static function apply(string $title): string
    {
        $title = trim(Str::squish($title));

        if ($title === '' || ! self::isShouted($title)) {
            return $title;
        }

        $words = preg_split('/(\s+)/u', $title, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];
        $first = true;

        foreach ($words as $word) {
            if (trim($word) === '') {
                $out[] = $word;

                continue;
            }

            $kept = self::keep($word);

            if ($kept !== null) {
                $out[] = $kept;
                $first = false;

                continue;
            }

            $lower = mb_strtolower($word);
            $out[] = $first ? self::upperFirst($lower) : $lower;
            $first = false;
        }

        $result = implode('', $out);

        // A sentence that ends and begins again inside one headline: the
        // word after a full stop or a colon takes a capital.
        return preg_replace_callback(
            '/([.:!?]\s+)(\p{Ll})/u',
            fn (array $m) => $m[1].mb_strtoupper($m[2]),
            $result
        ) ?? $result;
    }

    /** True when more than two-thirds of the letters are capitals. */
    public static function isShouted(string $title): bool
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $title) ?? '';

        if (mb_strlen($letters) < 4) {
            return false;
        }

        $upper = preg_replace('/[^\p{Lu}]/u', '', $letters) ?? '';

        return mb_strlen($upper) / mb_strlen($letters) > 0.66;
    }

    /**
     * The acronym form of a word, or null if it is an ordinary word.
     *
     * Punctuation attached to the word is preserved: "(EXCO)" and "EXCO,"
     * both have to come back with their brackets and commas.
     */
    private static function keep(string $word): ?string
    {
        if (preg_match('/^(\W*)([\w\-]+)(\W*)$/u', $word, $m) !== 1) {
            return null;
        }

        foreach (self::KEEP as $acronym) {
            if (mb_strtolower($m[2]) === mb_strtolower($acronym)) {
                return $m[1].$acronym.$m[3];
            }
        }

        foreach (self::PROPER as $noun) {
            if (mb_strtolower($m[2]) === mb_strtolower($noun)) {
                return $m[1].$noun.$m[3];
            }
        }

        // An ordinal such as 15TH or 46TH: the number stays, the suffix
        // goes down.
        if (preg_match('/^(\d+)(ST|ND|RD|TH)$/i', $m[2], $ordinal) === 1) {
            return $m[1].$ordinal[1].mb_strtolower($ordinal[2]).$m[3];
        }

        return null;
    }

    private static function upperFirst(string $word): string
    {
        return mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
    }

    /** Whether the import changed this title's case. */
    public static function wasRecased(string $original): bool
    {
        return self::apply($original) !== trim(\Illuminate\Support\Str::squish($original));
    }
}
