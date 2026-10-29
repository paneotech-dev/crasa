<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    /*
     * Audited like the other taxonomy kinds: a tag deleted under Content >
     * Taxonomies left no trace in the log.
     */
    /** @use HasFactory<TagFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['slug', 'sort_order', 'is_sample'];

    protected function casts(): array
    {
        return ['is_sample' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The order set under Content > Taxonomies, then the slug, as before. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('slug');
    }

    public function newsItems(): MorphToMany
    {
        return $this->morphedByMany(NewsItem::class, 'taggable');
    }
}
