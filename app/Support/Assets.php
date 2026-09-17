<?php

namespace App\Support;

/**
 * Versioned URLs for the static assets.
 *
 * The platform has no build step, so the stylesheets and scripts are served
 * from public/ exactly as they are written. That is deliberate and it is not
 * the problem. The problem was that the href never changed:
 *
 *   <link rel="stylesheet" href="/css/blocks.css">
 *
 * Cloudflare serves those files with cache-control: max-age=31536000. A
 * browser that fetched /css/blocks.css once holds it for a year and never
 * asks again, because nothing about the request has changed. The HTML is
 * no-cache and always fresh, so the markup arrived new and the styling
 * arrived old, and the site rendered as it had before the last several
 * runs: black map polygons, stacked definition lists, bulleted committees.
 *
 * Appending the file's modification time makes a changed file a changed
 * URL. The long max-age then becomes correct rather than harmful, which is
 * what a year-long TTL is for.
 *
 * filemtime rather than a content hash: one stat instead of reading 90KB on
 * every request, and it changes whenever the file does. The value is
 * memoised per request because a page asks for eight of these.
 */
final class Assets
{
    /** @var array<string, string> */
    private static array $versions = [];

    public static function url(string $path): string
    {
        return asset($path).'?v='.self::version($path);
    }

    /**
     * The version for one path.
     *
     * A missing file returns "0" rather than throwing. A stylesheet that is
     * not there is a problem, but it is not one that should take the whole
     * page down on the way to being noticed, and the guard test asserts the
     * files exist.
     */
    public static function version(string $path): string
    {
        if (isset(self::$versions[$path])) {
            return self::$versions[$path];
        }

        $file = public_path(ltrim($path, '/'));

        return self::$versions[$path] = is_file($file)
            ? (string) filemtime($file)
            : '0';
    }

    /**
     * Forget the memoised versions, as a new request would. Only the tests
     * need this.
     *
     * PHP also resets its stat cache between requests, and a test runs in
     * one process, so that is cleared here too. On PHP 8.3.6 (Ubuntu 24.04)
     * touch() does not clear it, and filemtime() kept returning the old time.
     */
    public static function flush(): void
    {
        self::$versions = [];
        clearstatcache();
    }
}
