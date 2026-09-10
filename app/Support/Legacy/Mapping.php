<?php

namespace App\Support\Legacy;

use Illuminate\Support\Str;

/**
 * The taxonomy ruling, in one place.
 *
 * WHY THE RULING IS CODE AND NOT COMMENTS IN A COMMAND. Deciding which
 * legacy category becomes a publication and which becomes a news item is a
 * decision about CRASA's own taxonomy. It was made once, in writing, and
 * the import must be able to be re-run without re-making it. Every rule
 * below is one line of that ruling and every one is tested.
 *
 * THE TITLE BEATS THE CATEGORY. Where a guideline's title names a sector
 * and the legacy category disagrees, the title wins and the row is flagged
 * for review. Two of the electronic communications guidelines name
 * broadcasting in their titles; they land in broadcasting with a flag,
 * rather than silently in telecommunications where the legacy menu put
 * them.
 */
class Mapping
{
    /**
     * Legacy category name to what it becomes.
     *
     * @var array<string, array{kind: string, type: string, sector?: string, archived?: bool}>
     */
    public const CATEGORIES = [
        'Annual Reports' => ['kind' => 'publication', 'type' => 'annual_report'],
        'Postal Regulatory Guidelines' => ['kind' => 'publication', 'type' => 'guideline', 'sector' => 'postal'],
        'Electronic Communications Regulatory Guidelines' => ['kind' => 'publication', 'type' => 'guideline', 'sector' => 'telecommunications'],
        'Press Releases' => ['kind' => 'news', 'type' => 'press_release'],
        'Stakeholder Consultations' => ['kind' => 'news', 'type' => 'consultation'],
        'Tenders' => ['kind' => 'news', 'type' => 'tender'],
        'CRASA News' => ['kind' => 'news', 'type' => 'news'],
        'Members News' => ['kind' => 'news', 'type' => 'news'],
        'Industry News' => ['kind' => 'news', 'type' => 'news'],
        'Events' => ['kind' => 'meeting', 'type' => 'meeting'],
    ];

    /**
     * Listing pages, which become no record and still have to redirect.
     *
     * A regulator who bookmarked /post-category/tenders is entitled to
     * land on the tenders filter rather than on a 404.
     *
     * @var array<string, string>
     */
    public const LISTINGS = [
        '/' => 'home',
        '/about' => 'about',
        '/contact' => 'contact',
        '/publications' => 'publications',
        '/post-category/annual-reports' => 'publications?type=annual_report',
        '/post-category/postal-regulatory-guidelines' => 'publications?type=guideline&sector=postal',
        '/post-category/electronic-communications-regulatory-guidelines' => 'publications?type=guideline&sector=telecommunications',
        '/post-category/press-releases' => 'newsroom?category=press_release',
        '/post-category/consultancies' => 'newsroom?category=consultation',
        '/post-category/tenders' => 'newsroom?category=tender',
        '/post-category/events' => 'events',
        '/news/current-news' => 'newsroom',
        '/news/archived-news' => 'newsroom?archived=1',
        '/blog-categories/crasa-news' => 'newsroom',
        '/blog-categories/members-news' => 'newsroom',
        '/blog-categories/advice' => 'newsroom',
        '/projects/current-projects' => 'projects',
        '/projects/past-projects' => 'projects?state=past',
    ];

    /**
     * Words in a title that name a sector.
     *
     * Postal is checked first and broadcasting second, because "SADC ICT
     * and Broadcasting Infrastructure Sharing Guidelines" names two and the
     * narrower of the two is the one that tells a reader where to file it.
     *
     * @var array<string, list<string>>
     */
    private const SECTOR_WORDS = [
        'postal' => ['postal', 'universal postal', 'e-commerce and trade facilitation', 'ecommerce and trade facilitation'],
        'broadcasting' => ['broadcasting', 'broadcast'],
    ];

    /** @return array{kind: string, type: string, sector?: string}|null */
    public static function forCategory(?string $category): ?array
    {
        return self::CATEGORIES[$category] ?? null;
    }

    /**
     * The sector this item belongs in, and whether that disagrees with the
     * legacy category.
     *
     * @return array{sector: ?string, from_title: bool}
     */
    public static function sectorFor(string $title, ?string $categorySector): array
    {
        $haystack = Str::lower($title);

        foreach (self::SECTOR_WORDS as $sector => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    return [
                        'sector' => $sector,
                        'from_title' => $sector !== $categorySector,
                    ];
                }
            }
        }

        return ['sector' => $categorySector, 'from_title' => false];
    }

    /**
     * The publication year written into a CDN filename.
     *
     * Webflow prefixes every upload with a hex id, so the year is looked
     * for after that prefix: "6127444d969e0b4cd2348592_2000 INTERCONNECTION
     * GUIDELINES FOR SADC.pdf" is a 2000 document, not a 6127 one.
     */
    public static function yearInFilename(string $filename): ?int
    {
        $name = rawurldecode(basename($filename));
        $name = preg_replace('/^[0-9a-f]{16,}_/i', '', $name) ?? $name;

        /*
         * Bounded by "not a digit" rather than by \b, because the filenames
         * separate the year from the title with an underscore -- "2011_SADC
         * TOOLKIT" -- and an underscore is a word character, so \b never
         * fires between them.
         */
        if (preg_match('/(?<!\d)(19[89]\d|20[0-4]\d)(?!\d)/', $name, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** The edition year a publication page states in its standfirst. */
    public static function editionYear(?string $summary): ?int
    {
        if ($summary !== null && preg_match('/\bEdition\s+((?:19|20)\d{2})\b/i', $summary, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * The original filename, with Webflow's upload prefix removed.
     *
     * The prefix is Webflow's, not CRASA's. What the Secretariat named the
     * file is what a reader should be offered, and it is what the ruling
     * means by keeping the original filename.
     */
    public static function originalFilename(string $url): string
    {
        $name = rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?: $url));

        return preg_replace('/^[0-9a-f]{16,}_/i', '', $name) ?: $name;
    }

    /**
     * A slug from the title rather than from the legacy path.
     *
     * The legacy site is built on a Webflow template and several CRASA
     * articles kept the template post's slug: the page at
     * /post/brand-optics-and-what-it-means-for-your-business is a
     * masterclass on broadband gap analysis. Carrying that slug forward
     * would put a marketing phrase in a regulator's citation. The legacy
     * URL still redirects, so nothing is lost.
     */
    public static function slugFor(string $title, string $legacyUrl): string
    {
        $slug = Str::slug(Str::limit($title, 180, ''));

        return $slug !== '' ? $slug : Str::slug(basename(parse_url($legacyUrl, PHP_URL_PATH) ?: 'item'));
    }
}
