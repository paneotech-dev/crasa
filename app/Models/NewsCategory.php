<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\IsTaxonomyTerm;
use App\Support\BadgeColours;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A newsroom category: news, tender, public consultation, communique,
 * press release, and whatever the Secretariat adds under Content >
 * Taxonomies. Replaced the App\Enums\NewsCategory enum on 29 October 2026;
 * the migration seeded the enum's values unchanged.
 */
class NewsCategory extends Model
{
    use Auditable, HasTranslations, IsTaxonomyTerm;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['slug', 'sort_order', 'colour'];

    public function newsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class, 'category', 'slug');
    }

    /** The badge's background and text, as token references. */
    public function badgeStyle(): string
    {
        return BadgeColours::style($this->colour);
    }
}
