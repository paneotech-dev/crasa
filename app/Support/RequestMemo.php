<?php

namespace App\Support;

use Closure;

/**
 * A value worked out once per request and reused for the rest of it.
 *
 * The header, the footer and the blocks ask the same questions many times
 * while one page renders (the site settings, which anchored sections are
 * unwritten, whether the menu table exists). Each ask was a query. The
 * answer is kept on the request itself, so it can never outlive the page
 * it was computed for: the next request, or the next request a test makes,
 * starts empty.
 */
final class RequestMemo
{
    private const PREFIX = 'request-memo.';

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $key, Closure $compute): mixed
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::PREFIX.$key)) {
            $attributes->set(self::PREFIX.$key, $compute());
        }

        return $attributes->get(self::PREFIX.$key);
    }

    public static function forget(string $key): void
    {
        request()->attributes->remove(self::PREFIX.$key);
    }
}
