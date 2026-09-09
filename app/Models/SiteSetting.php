<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One editable value that belongs to the site rather than to a page.
 *
 * Social accounts, the legacy site's address, anything the Secretariat has
 * to be able to change without a deploy.
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    protected static function booted(): void
    {
        // The public surfaces read these on every request. Forget the cache
        // whenever one changes, rather than making every reader wait for a
        // query that answers the same thing all day.
        static::saved(fn () => Cache::forget('site-settings'));
        static::deleted(fn () => Cache::forget('site-settings'));
    }

    /** @return array<string, string|null> */
    public static function all_values(): array
    {
        return Cache::rememberForever('site-settings', fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::all_values()[$key] ?? null;

        return filled($value) ? $value : $default;
    }
}
