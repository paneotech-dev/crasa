<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Turns a pasted post address into what the site needs to draw it.
 *
 * X: the public oEmbed endpoint (publish.twitter.com/oembed) needs no token
 * and returns a blockquote that X's widget script turns into the post. It is
 * fetched once, on save, and stored.
 * Facebook: the single-post plugin frame (facebook.com/plugins/post.php).
 * LinkedIn: the public embed frame (linkedin.com/embed/feed/update/<urn>).
 *
 * Anything that cannot be resolved is still saved. It is drawn as a card
 * with the network's mark, the caption and "Open the post", never as an
 * empty frame.
 */
final class SocialEmbed
{
    public const X_OEMBED = 'https://publish.twitter.com/oembed';

    /** The network a post address belongs to, or null. */
    public static function network(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.|mobile\.)/', '', $host);

        return match (true) {
            in_array($host, ['x.com', 'twitter.com'], true) => 'x',
            in_array($host, ['facebook.com', 'fb.com', 'fb.watch'], true) => 'facebook',
            in_array($host, ['linkedin.com'], true) => 'linkedin',
            default => null,
        };
    }

    /** Whether the address points at one post rather than a profile or page. */
    public static function isPost(string $url): bool
    {
        return match (self::network($url)) {
            'x' => (bool) preg_match('~/status(?:es)?/\d+~', $url),
            'facebook' => (bool) preg_match('~(/posts/|/videos/|/photos?/|story_fbid=|/permalink/|pfbid|fbid=|/reel/)~', $url),
            'linkedin' => self::linkedinUrn($url) !== null,
            default => false,
        };
    }

    /**
     * The URN LinkedIn's embed frame takes, read off a post address or an
     * embed address: urn:li:activity:<id>, urn:li:share:<id> or
     * urn:li:ugcPost:<id>.
     */
    public static function linkedinUrn(string $url): ?string
    {
        $decoded = urldecode($url);

        if (preg_match('~urn:li:(activity|share|ugcPost):(\d{10,})~', $decoded, $m)) {
            return 'urn:li:'.$m[1].':'.$m[2];
        }

        if (preg_match('~-(activity|share|ugcPost)-(\d{10,})~', $decoded, $m)) {
            return 'urn:li:'.$m[1].':'.$m[2];
        }

        return null;
    }

    /** The frame address for Facebook and LinkedIn; null for X. */
    public static function frameUrl(string $url): ?string
    {
        return match (self::network($url)) {
            'facebook' => 'https://www.facebook.com/plugins/post.php?'.http_build_query([
                'href' => $url,
                'show_text' => 'true',
                'width' => 500,
            ]),
            'linkedin' => ($urn = self::linkedinUrn($url)) ? 'https://www.linkedin.com/embed/feed/update/'.$urn : null,
            default => null,
        };
    }

    /**
     * X's oEmbed markup for a post, or null when X does not answer.
     *
     * The script tag is left out (omit_script) and stripped again in case
     * it comes back: the page loads X's widget script once, from the CSP's
     * allowed host, rather than once per post from inside stored markup.
     *
     * @return array{html: string, author: string|null}|null
     */
    public static function xOembed(string $url): ?array
    {
        try {
            $response = Http::timeout(8)->acceptJson()->get(self::X_OEMBED, [
                'url' => $url,
                'omit_script' => 1,
                'dnt' => 'true',
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $html = self::cleanXHtml((string) $response->json('html'));

        return $html === null ? null : ['html' => $html, 'author' => $response->json('author_name')];
    }

    /**
     * Only a twitter-tweet blockquote is kept, built from an allowlist: the
     * cleaned markup is shown before cookie consent, so nothing in it may
     * load anything. Allowed: <blockquote> (class, the widget's data-*
     * attributes), <p> (lang, dir), <a> (an http or https href only) and
     * <br>. Every other element is dropped (script, style and media with
     * their content), every other attribute removed. Null when the markup
     * is not a tweet blockquote or no text is left.
     */
    public static function cleanXHtml(string $html): ?string
    {
        $html = trim((string) preg_replace(
            '~<(script|style|svg|math|template|noscript|iframe|object|video|audio|picture|title|textarea|select)\b[^>]*>.*?</\1\s*>~is',
            '', $html,
        ));

        if (! preg_match('~^<blockquote class="twitter-tweet"[^>]*>.*</blockquote>$~is', $html)) {
            return null;
        }

        $html = strip_tags($html, '<blockquote><p><a><br>');
        $html = (string) preg_replace_callback('~<(/?)(blockquote|p|a|br)\b([^>]*)>~i', function (array $m): string {
            $tag = strtolower($m[2]);

            if ($m[1] === '/') {
                return $tag === 'br' ? '' : '</'.$tag.'>';
            }

            return '<'.$tag.self::allowedAttributes($tag, $m[3]).'>';
        }, $html);

        if (! preg_match('~^<blockquote class="twitter-tweet"[^>]*>.*</blockquote>$~is', $html)
            || preg_match('~\son[a-z]+\s*=|javascript:|<(iframe|object|embed|style|form)\b~i', $html)
            || trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) === '') {
            return null;
        }

        // The separator X writes before the author is a long dash; the
        // house style has none, and the widget replaces the text anyway.
        return str_replace(['&mdash;', "\u{2014}"], '-', $html);
    }

    /** The attributes kept on one allowed element, escaped again. */
    private static function allowedAttributes(string $tag, string $raw): string
    {
        preg_match_all('~([a-zA-Z][a-zA-Z0-9:_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~', $raw, $found, PREG_SET_ORDER);
        $kept = '';

        foreach ($found as $attr) {
            $name = strtolower($attr[1]);
            $value = html_entity_decode($attr[2] !== '' ? $attr[2] : (($attr[3] ?? '') !== '' ? $attr[3] : ($attr[4] ?? '')), ENT_QUOTES | ENT_HTML5);

            $allowed = match ($tag) {
                'blockquote' => ($name === 'class' && $value === 'twitter-tweet') || preg_match('~^data-[a-z-]+$~', $name),
                'p' => in_array($name, ['lang', 'dir'], true),
                'a' => $name === 'href' && preg_match('~^https?://~i', $value),
                default => false,
            };

            if ($allowed) {
                $kept .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_HTML5).'"';
            }
        }

        return $kept;
    }

    /**
     * Everything to store for a pasted address.
     *
     * @return array{network: string, embed_html: string|null, embed_url: string|null}
     */
    public static function resolve(string $url): array
    {
        $network = (string) self::network($url);
        $html = $network === 'x' ? (self::xOembed($url)['html'] ?? null) : null;

        return [
            'network' => $network,
            'embed_html' => $html,
            'embed_url' => self::frameUrl($url),
        ];
    }

    /** The post's date, read off the id where the network encodes it. */
    public static function dateFromId(string $url): ?\Illuminate\Support\Carbon
    {
        $id = null;

        if (self::network($url) === 'x' && preg_match('~/status(?:es)?/(\d{15,})~', $url, $m)) {
            $id = $m[1];
            $epoch = 1288834974657; // X's snowflake epoch
        } elseif (self::network($url) === 'linkedin' && ($urn = self::linkedinUrn($url))) {
            $id = substr($urn, strrpos($urn, ':') + 1);
            $epoch = 0;
        }

        if ($id === null || PHP_INT_SIZE < 8) {
            return null;
        }

        $ms = ((int) $id >> 22) + $epoch;

        return $ms > 1_000_000_000_000 ? \Illuminate\Support\Carbon::createFromTimestampMs($ms) : null;
    }
}
