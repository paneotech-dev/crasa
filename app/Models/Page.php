<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use App\Support\Locales;
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
            'status' => PageStatus::class,
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
        return $query->where('status', PageStatus::Published);
    }

    /**
     * Whether a block on this page already renders the h1.
     *
     * Only the hero does. A page built from rich text and grids alone has
     * headings starting at level 2 and no page title in the document
     * outline, which is a structure failure under WCAG 2.1 AA and against
     * the one-h1-per-page rule in the design foundations.
     */
    public function blocksRenderH1(): bool
    {
        return $this->visibleBlocks
            ->contains(fn (PageBlock $block) => $block->type() === BlockType::Hero);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    /**
     * Every block in editing order, including the hidden ones. The public
     * side asks for visibleBlocks; the back office has to see what it is
     * being asked to unhide.
     */
    public function editableBlocks(): HasMany
    {
        return $this->blocks()->with('translations');
    }

    /**
     * Which languages this page carries a title in. Screen 14 puts a
     * language column on every content row and tints the row when one is
     * missing, so this is what that column reads.
     *
     * @return list<string>
     */
    public function localesWithTitle(): array
    {
        return $this->translations
            ->where('field', 'title')
            ->reject(fn ($t) => trim((string) $t->value) === '')
            ->pluck('locale')
            ->intersect(Locales::SUPPORTED)
            ->values()
            ->all();
    }
}
