<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'meta_title', 'meta_description'];

    protected $fillable = ['slug', 'template', 'status', 'published_at', 'is_sample'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('position');
    }

    /** Visible blocks, with their translations loaded in one query. */
    public function visibleBlocks(): HasMany
    {
        return $this->blocks()->where('is_visible', true)->with('translations');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
