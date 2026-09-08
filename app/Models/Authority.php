<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\AuthorityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A national regulatory authority.
 */
class Authority extends Model
{
    /** @use HasFactory<AuthorityFactory> */
    use Auditable, HasFactory;

    use MarksSampleData;

    protected $table = 'authorities';

    protected $fillable = [
        'country_id',
        'name',
        'acronym',
        'website',
        'established_year',
        'logo_path',
        'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'established_year' => 'integer',
            'is_sample' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** The sectors this authority regulates. */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class)->withTimestamps();
    }

    public function chairedCommittees(): HasMany
    {
        return $this->hasMany(Committee::class, 'chair_authority_id');
    }

    public function regulates(string $sectorSlug): bool
    {
        return $this->sectors->contains('slug', $sectorSlug);
    }

    /** Initials for the avatar, taken from the authority code. */
    public function initials(): string
    {
        return strtoupper(substr($this->acronym, 0, 2));
    }
}
