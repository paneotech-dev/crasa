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
     * Only a twitter-tweet blockquote is kept. No script, no event handler,
     * no other element at the top level.
     */
    public static function cleanXHtml(string $html): ?string
    {
        $html = trim(preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html));

        if (! preg_match('~^<blockquote class="twitter-tweet"[^>]*>.*</blockquote>$~is', $html)) {
            return null;
        }

        if (preg_match('~\son[a-z]+\s*=|javascript:|<(iframe|object|embed|style|form)\b~i', $html)) {
            return null;
        }

        // The separator X writes before the author is a long dash; the
        // house style has none, and the widget replaces the text anyway.
        return str_replace(['&mdash;', "\u{2014}"], '-', $html);
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
