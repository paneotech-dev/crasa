<?php

namespace App\Models;

use App\Enums\Clearance;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use App\Models\Scopes\CountryScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * A folder in the extranet. IN-02.
 *
 * ZERO TRUST, AND THE SCOPE IS WHERE IT LIVES. Screen 12: "The other
 * thirteen member states' folders are not listed above, are not searchable,
 * and are not reachable by guessing a URL."
 *
 * That is three separate claims and one mechanism. A global scope on
 * country_id makes all three true at once: a folder belonging to another
 * member state is not in any listing, not in any search, and not findable
 * by id, because the query that would have found it returns nothing. A
 * controller check would have satisfied the third and quietly failed the
 * first two.
 *
 * A regional folder has a null country_id and is shared. A committee folder
 * is gated by committee membership on top, which the policy handles.
 */
class Folder extends Model
{
    use Auditable, HasFactory, MarksSampleData, SoftDeletes;

    protected $fillable = ['parent_id', 'country_id', 'committee_id', 'name', 'slug', 'clearance', 'is_sample'];

    protected function casts(): array
    {
        return ['clearance' => Clearance::class, 'is_sample' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CountryScope);

        static::saving(function (Folder $folder): void {
            /*
             * A folder cannot be less restricted than its parent. Otherwise
             * a Confidential committee folder could hold a Member subfolder
             * and the tree would leak downwards, which is the failure mode
             * nobody notices until it matters.
             */
            $parent = $folder->parent_id ? Folder::withoutGlobalScopes()->find($folder->parent_id) : null;

            if ($parent && $folder->clearance->rank() < $parent->clearance->rank()) {
                throw new RuntimeException(
                    'A folder cannot be less restricted than the folder that contains it. '
                    .$parent->name.' is '.$parent->clearance->value.'.'
                );
            }
        });
    }

    public function countryScopeColumn(): string
    {
        return 'country_id';
    }

    /** A regional folder belongs to no member state and is shared. */
    public function countryScopeIncludesShared(): bool
    {
        return true;
    }

    /** A committee folder is opened by a seat on that committee. */
    public function countryScopeCommitteeColumn(): ?string
    {
        return 'committee_id';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** The breadcrumb, root first. */
    public function ancestors(): array
    {
        $path = [];
        $folder = $this->parent;

        while ($folder !== null) {
            array_unshift($path, $folder);
            $folder = $folder->parent;
        }

        return $path;
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->documents()->sum('size_bytes');

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(0, (int) round($bytes / 1024)).' KB';
    }
}
