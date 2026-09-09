<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use App\Support\Locales;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * One item in a menu. Screen 14's navigation builder edits these.
 *
 * The label is translated, the destination is not: a route name and a page
 * slug are the same in every language, and the language segment is added at
 * render.
 */
class MenuItem extends Model
{
    use Auditable, HasFactory, HasTranslations, MarksSampleData;

    public const MENUS = ['header', 'footer'];

    public const TYPES = ['route', 'page', 'url'];

    /** @var list<string> */
    protected array $translatable = ['label'];

    protected $fillable = [
        'menu', 'parent_id', 'position', 'destination_type', 'destination',
        'is_visible', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeInMenu(Builder $query, string $menu): Builder
    {
        return $query->where('menu', $menu);
    }

    /**
     * Where this item points, in the reader's language, or null.
     *
     * NULL IS THE IMPORTANT RETURN. A menu item can outlive its
     * destination: a page is deleted, a route is renamed in a refactor, an
     * editor mistypes a slug. Every one of those has to skip the item, not
     * take the header down on every page of the site at once.
     *
     * A footer column heading legitimately has no destination and returns
     * null too, which is why callers ask hasDestination() rather than
     * treating null as an error.
     */
    public function url(): ?string
    {
        $locale = app()->getLocale();

        return match ($this->destination_type) {
            'route' => $this->destination && Route::has($this->destination)
                ? route($this->destination, ['locale' => $locale])
                : null,

            // Resolved through the Page record rather than as a path, so a
            // page that is unpublished or deleted takes its menu item out
            // of the navigation instead of leaving a link to a 404.
            'page' => $this->pageUrl($locale),

            'url' => Navigation::resolve($this->destination),

            default => null,
        };
    }

    public function hasDestination(): bool
    {
        return filled($this->destination);
    }

    /**
     * Whether the destination still exists.
     *
     * Reported in the builder so the Secretariat sees a broken item there
     * rather than discovering it as a hole in the header.
     */
    public function destinationIsResolvable(): bool
    {
        return ! $this->hasDestination() || $this->url() !== null;
    }

    /**
     * The label a reader sees.
     *
     * Screen 14: "A menu item with an untranslated label shows the English
     * text on the translated site and is flagged here, rather than
     * disappearing from the navigation." A navigation item that vanishes in
     * French is worse than one that is briefly in English.
     */
    public function labelFor(?string $locale = null): string
    {
        return $this->translated('label', '', $locale) ?: '';
    }

    /** @return list<string> the locales this item still has no label in */
    public function missingLocales(): array
    {
        return array_values(array_filter(
            Locales::SUPPORTED,
            fn (string $locale) => $this->translations
                ->where('field', 'label')
                ->where('locale', $locale)
                ->whereNotNull('value')
                ->isEmpty()
        ));
    }

    private function pageUrl(string $locale): ?string
    {
        $page = Page::where('slug', $this->destination)->first();

        if ($page === null || ! $page->isPublished()) {
            return null;
        }

        return url($locale.'/'.$page->slug);
    }
}
