<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One person at a national regulator. Extranet item 4.
 *
 * NOT COUNTRY-SCOPED FOR READING, AND SCOPED FOR WRITING. The point of a
 * regional directory is that a Zambian regulator can find the right person
 * in Malawi. What a representative may not do is add, edit or delete
 * somebody at another member state's authority, which the controller
 * enforces on every write.
 *
 * CONSENT GATES THE READ. `listed()` is the only scope any directory query
 * uses, and it requires `listed_at`. A row whose person has not consented
 * exists for its own representative to manage and appears to nobody else.
 */
class AuthorityContact extends Model
{
    use Auditable, MarksSampleData, SoftDeletes;

    protected $fillable = [
        'authority_id', 'country_id', 'name', 'role', 'email', 'phone',
        'listed_at', 'listed_by', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'listed_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Authority::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'authority_contact_sector');
    }

    /** Only people who have said their details may be shown. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereNotNull('listed_at');
    }

    public function isListed(): bool
    {
        return $this->listed_at !== null;
    }
}
