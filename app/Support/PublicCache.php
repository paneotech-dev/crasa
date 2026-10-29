<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Ten minutes of memory for the expensive figures on public pages.
 *
 * The homepage recomputed its member counts, its latest news and
 * publications, the comparison defaults and the hero figures on every
 * visit: about seventy queries for a page that changes a few times a week.
 * Each of those is now worked out once and kept for ten minutes, per
 * language, because the labels inside them are translated.
 *
 * Only plain values are kept: arrays, strings, numbers. The cache store
 * refuses to unserialize objects (cache.serializable_classes is false), so
 * a value holding a model or a collection is computed and returned as it
 * is, never stored.
 *
 * Saving any record these figures are built from moves a version number,
 * which retires every entry at once (see AppServiceProvider). A bulk update
 * that bypasses model events is picked up when the ten minutes run out.
 */
final class PublicCache
{
    public const TTL = 600;

    /**
     * Raised whenever the shape of a cached value changes, so a deploy
     * never serves an entry written by the previous release.
     */
    private const SHAPE = 2;

    private const VERSION_KEY = 'public-cache-version';

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $name, Closure $compute): mixed
    {
        // The host is part of the key because the values carry absolute
        // links, and a preview and the live site share nothing else.
        $key = sprintf(
            'public:%d.%s:%s:%s:%s',
            self::SHAPE,
            self::version(),
            substr(md5(request()->getSchemeAndHttpHost()), 0, 8),
            app()->getLocale(),
            $name,
        );

        return RequestMemo::remember($key, function () use ($key, $compute) {
            $cached = Cache::get($key);

            if ($cached !== null) {
                return $cached;
            }

            $value = $compute();

            if ($value !== null && self::isPlain($value)) {
                Cache::put($key, $value, self::TTL);
            }

            return $value;
        });
    }

    private static function isPlain(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isPlain($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    /** Retire every entry, for when a record they are built from changes. */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) hrtime(true));
        RequestMemo::forget('public-cache-version');
    }

    private static function version(): string
    {
        return RequestMemo::remember('public-cache-version', fn () => (string) Cache::rememberForever(self::VERSION_KEY, fn () => '1'));
    }
}
