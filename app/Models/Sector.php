<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalisedNames;
use App\Support\Locales;
use App\Support\RequestMemo;
use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A regulated sector: telecommunications, broadcasting and postal, as
 * seeded. Since 29 October 2026 maintained under Content > Taxonomies,
 * with an order and its French and Portuguese names in the translations
 * table; a sector in use cannot be deleted.
 */
class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use Auditable, HasFactory;

    use HasLocalisedNames {
        localisedName as langFileName;
    }

    /** French and Portuguese names: lang/{fr,pt}/names.php. */
    protected function localisedNames(): array
    {
        return ['group' => 'sectors', 'key' => 'slug', 'fields' => ['name']];
    }

    public const TELECOMMUNICATIONS = 'telecommunications';

    public const BROADCASTING = 'broadcasting';

    public const POSTAL = 'postal';

    /** @var list<string> */
    public const SLUGS = [
        self::TELECOMMUNICATIONS,
        self::BROADCASTING,
        self::POSTAL,
    ];

    protected $fillable = ['slug', 'sort_order', 'name', 'icon', 'accent_token'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetNames());
        static::deleted(fn () => self::forgetNames());
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** French and Portuguese names written under Content > Taxonomies. */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * The name in the page language: the translations table first, then
     * lang/{fr,pt}/names.php, then the English column. Never in the back
     * office, for the reason HasLocalisedNames gives.
     */
    private function localisedName(string $field, string $value, array $spec): string
    {
        $locale = app()->getLocale();
        $route = app()->bound('request') ? (string) request()->route()?->getName() : '';

        if ($locale === Locales::FALLBACK || (str_starts_with($route, 'admin.') && ! str_ends_with($route, '.preview') && ! self::$localiseInBackOffice)) {
            return $value;
        }

        $stored = self::storedNames()[$this->getKey()][$locale] ?? null;

        return is_string($stored) && trim($stored) !== '' ? $stored : $this->langFileName($field, $value, $spec);
    }

    /** @return array<int, array<string, string>> sector id => locale => name */
    public static function storedNames(): array
    {
        if (! app()->bound('request')) {
            return self::readNames();
        }

        return RequestMemo::remember('sector-names', fn () => self::readNames());
    }

    public static function forgetNames(): void
    {
        if (app()->bound('request')) {
            RequestMemo::forget('sector-names');
        }
    }

    /** @return array<int, array<string, string>> */
    private static function readNames(): array
    {
        $names = [];

        foreach (Translation::query()->where('translatable_type', self::class)->where('field', 'name')->get(['translatable_id', 'locale', 'value']) as $row) {
            $names[$row->translatable_id][$row->locale] = (string) $row->value;
        }

        return $names;
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    /** The regulators holding a mandate in this sector. */
    public function authorities(): BelongsToMany
    {
        return $this->belongsToMany(Authority::class)->withTimestamps();
    }
}
