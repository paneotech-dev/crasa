<?php

namespace App\Support\Legacy;

use Illuminate\Support\Str;

/**
 * What one legacy page says, pulled out of Webflow's markup.
 *
 * WHY A PARSER AND NOT A REGEX AT THE CALL SITE. The legacy site is
 * Webflow, which means the markup is machine-generated and therefore
 * completely regular: every article body is `div.article.w-richtext`,
 * every category is the first breadcrumb link or the article-meta badge,
 * every date is the text node beside that badge. That regularity is worth
 * naming once and testing once, because the import runs against a site
 * that will be switched off and the parse cannot be repeated later.
 *
 * WEBFLOW'S IMAGE VARIANTS. An `img` carries a srcset of derivatives named
 * `<base>-p-500.jpg`, `<base>-p-1080.jpeg` and so on, with the original as
 * the widest candidate. The brief says take the largest that exists, so
 * `largestImage()` reads the srcset's widths and returns the widest URL
 * rather than the `src`, which is often a thumbnail.
 */
class PageParser
{
    /** @return array<string, mixed> */
    public static function parse(string $url, string $html): array
    {
        $body = self::section($html);

        return [
            'url' => $url,
            'title' => self::heading($body) ?: self::title($html),
            'meta_title' => self::title($html),
            'summary' => self::summary($html, $body),
            'body_html' => self::articleBody($body),
            'category' => self::category($body),
            'date_text' => self::dateText($body),
            'hero_image' => self::heroImage($body),
            'documents' => self::documents($body),
            'external_documents' => self::externalDocuments($body),
            'images' => self::images($body),
            'event_dates' => self::eventDates($body),
            'child_links' => self::childLinks($body),
        ];
    }

    /**
     * The page between the navigation and the footer.
     *
     * Everything above `menu-button-close-image` is the shared navbar and
     * everything from `class="footer-1"` down is the shared footer. Both
     * repeat on all ninety-one pages, and both carry links and images that
     * would otherwise be harvested once per page as if they were content.
     */
    public static function section(string $html): string
    {
        $start = strpos($html, 'menu-button-close-image');
        $start = $start === false ? 0 : (int) strpos($html, '>', $start);

        $end = strpos($html, 'class="footer-1"');
        $end = $end === false ? strlen($html) : $end;

        return substr($html, $start, $end - $start);
    }

    private static function title(string $html): string
    {
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m);

        return self::text($m[1] ?? '');
    }

    private static function heading(string $body): string
    {
        preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $body, $m);

        return self::text($m[1] ?? '');
    }

    /**
     * The standfirst. Publications carry it as `div.text-large` under the
     * h1 — "Edition 2021" — and news items as `div.article-subtitle`. Both
     * fall back to the meta description, which Webflow fills from the same
     * field.
     */
    private static function summary(string $html, string $body): string
    {
        if (preg_match('/<div class="text-large article-subtitle[^"]*"[^>]*>(.*?)<\/div>/is', $body, $m)) {
            $text = self::text($m[1]);

            if ($text !== '') {
                return $text;
            }
        }

        if (preg_match('/<\/h1>\s*<div class="text-large"[^>]*>(.*?)<\/div>/is', $body, $m)) {
            $text = self::text($m[1]);

            if ($text !== '') {
                return $text;
            }
        }

        preg_match('/<meta content="([^"]*)" name="description"/i', $html, $m);

        return self::text($m[1] ?? '');
    }

    /**
     * The div matching $openPattern and everything it contains.
     *
     * Webflow nests divs several deep inside a rich-text field and inside
     * the meta row, so a non-greedy match to the next closing tag stops in
     * the middle. Counting opens against closes is the only way to find the
     * tag that belongs to this one.
     */
    public static function block(string $body, string $openPattern): string
    {
        if (! preg_match($openPattern, $body, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $open = (int) $m[0][1];
        $cursor = $open + strlen($m[0][0]);
        $depth = 1;

        while ($depth > 0 && preg_match('/<(\/?)div\b[^>]*>/i', $body, $tag, PREG_OFFSET_CAPTURE, $cursor)) {
            $depth += $tag[1][0] === '/' ? -1 : 1;
            $cursor = (int) $tag[0][1] + strlen($tag[0][0]);
        }

        return trim(substr($body, $open, $cursor - $open));
    }

    /**
     * The rich-text body.
     *
     * The class list is matched loosely because Webflow inserts its own
     * marker into it: an article with no text is
     * `article w-dyn-bind-empty w-richtext`, and an exact match on
     * `article w-richtext` silently returns nothing for every page that
     * carries an extra class.
     */
    private static function articleBody(string $body): string
    {
        return self::block($body, '/<div class="article[^"]*w-richtext"[^>]*>/i');
    }

    /** The first breadcrumb link, or the article-meta badge. */
    private static function category(string $body): ?string
    {
        if (preg_match('/<div class="breadcrumbs">\s*<a[^>]*>(.*?)<\/a>/is', $body, $m)) {
            return self::text($m[1]) ?: null;
        }

        if (preg_match('/class="badge article-meta-badge[^"]*"[^>]*>.*?<div[^>]*>(.*?)<\/div>/is', $body, $m)) {
            return self::text($m[1]) ?: null;
        }

        return null;
    }

    private static function dateText(string $body): ?string
    {
        $meta = self::block($body, '/<div class="article-meta"[^>]*>/i');

        if ($meta === '') {
            return null;
        }

        // The last div in the meta row is the date; the ones before it are
        // the category badge and its label.
        preg_match_all('/<div[^>]*>([^<]*)<\/div>/i', $meta, $divs);

        foreach (array_reverse($divs[1]) as $candidate) {
            $text = self::text($candidate);

            if ($text !== '' && strtotime($text) !== false) {
                return $text;
            }
        }

        return null;
    }

    private static function heroImage(string $body): ?string
    {
        if (! preg_match('/<div class="blog-main-image">(.*?)<\/div>/is', $body, $m)) {
            return null;
        }

        return self::largestImage($m[1]);
    }

    /**
     * The widest candidate in a srcset, falling back to src.
     *
     * A 150x150 thumbnail is what `src` often holds. The brief says follow
     * each thumbnail to its original, and Webflow states the original's
     * width in the srcset, so the widest candidate is the original.
     */
    public static function largestImage(string $imgHtml): ?string
    {
        if (preg_match('/srcset="([^"]+)"/i', $imgHtml, $m)) {
            $best = null;
            $bestWidth = -1;

            foreach (explode(',', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate));

                if ($parts === false || $parts[0] === '') {
                    continue;
                }

                $width = isset($parts[1]) ? (int) rtrim($parts[1], 'w') : 0;

                if ($width > $bestWidth) {
                    $bestWidth = $width;
                    $best = $parts[0];
                }
            }

            if ($best !== null) {
                return $best;
            }
        }

        if (preg_match('/src="([^"]+)"/i', $imgHtml, $m)) {
            $src = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $src === '' ? null : $src;
        }

        return null;
    }

    /** @return list<string> */
    private static function documents(string $body): array
    {
        preg_match_all('/href="([^"]+\.(?:pdf|docx?|xlsx?|pptx?))"/i', $body, $m);

        return array_values(array_unique(array_map(
            fn (string $u) => html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $m[1]
        )));
    }

    /**
     * A download button that points somewhere other than a file.
     *
     * The annual reports page is the reason this exists: its Download
     * button goes to a SharePoint folder, not a PDF, so a crawl that only
     * collects file extensions reports the category as empty. The platform
     * will not redirect a reader to SharePoint, so these are recorded for
     * the Secretariat to supply rather than followed.
     *
     * @return list<string>
     */
    private static function externalDocuments(string $body): array
    {
        preg_match_all('/<a\b[^>]*class="[^"]*\bbutton\b[^"]*"[^>]*>/i', $body, $m);

        $found = [];

        foreach ($m[0] as $tag) {
            // Webflow hides an unfilled button rather than omitting it.
            if (str_contains($tag, 'w-condition-invisible')) {
                continue;
            }

            if (! preg_match('/href="([^"#]+)"/i', $tag, $href)) {
                continue;
            }

            $url = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (str_starts_with($url, 'http') && ! preg_match('/\.(pdf|docx?|xlsx?|pptx?)(\?|$)/i', $url)) {
                $found[] = $url;
            }
        }

        return array_values(array_unique($found));
    }
    /** @return list<string> */
    private static function images(string $body): array
    {
        preg_match_all('/<img\b[^>]*>/i', $body, $m);

        $found = [];

        foreach ($m[0] as $tag) {
            $url = self::largestImage($tag);

            if ($url !== null && str_starts_with($url, 'http')) {
                $found[] = $url;
            }
        }

        return array_values(array_unique($found));
    }

    /** @return array{start: ?string, end: ?string} */
    private static function eventDates(string $body): array
    {
        if (! preg_match('/<div class="event-dates([^"]*)">(.*?)<\/div>\s*<\/div>/is', $body, $m)) {
            return ['start' => null, 'end' => null];
        }

        // Webflow marks an unfilled field with w-condition-invisible, so a
        // hidden block means the item genuinely has no dates.
        if (str_contains($m[1], 'w-condition-invisible')) {
            return ['start' => null, 'end' => null];
        }

        preg_match_all('/<div[^>]*>([^<]+)<\/div>/i', $m[2], $divs);

        $dates = [];

        foreach ($divs[1] as $candidate) {
            $text = self::text($candidate);

            if ($text !== '' && strtotime($text) !== false && preg_match('/\d/', $text)) {
                $dates[] = $text;
            }
        }

        return ['start' => $dates[0] ?? null, 'end' => $dates[1] ?? null];
    }

    /**
     * Links to items this page lists, so a listing page enqueues its own
     * children. Anything else on the page is chrome.
     *
     * @return list<string>
     */
    private static function childLinks(string $body): array
    {
        preg_match_all('/href="(\/(?:post|post-articles|project)\/[^"#?]+)"/i', $body, $m);

        return array_values(array_unique(array_map(
            fn (string $p) => 'https://www.crasa.org'.rtrim($p, '/'),
            $m[1]
        )));
    }

    public static function text(string $html): string
    {
        $text = preg_replace('/<[^>]*>/', ' ', $html) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2039}", "\u{203A}"], ["'", "'", '"', '"', "'", "'"], $text);

        return trim(Str::squish($text));
    }
}
