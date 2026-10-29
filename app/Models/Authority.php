<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalisedNames;
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
    use Auditable, HasFactory, HasLocalisedNames;

    /**
     * French and Portuguese names, when the Secretariat writes them in the
     * back office. There is no lang file group: an authority's own name is
     * what its statute says, and most have none in another language.
     */
    protected function localisedNames(): array
    {
        return ['group' => 'authorities', 'key' => null, 'fields' => ['name']];
    }

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
        'confirmed_at',
        'confirmed_by_id',
        'confirmed_by_name',
    ];

    protected function casts(): array
    {
        return [
            'established_year' => 'integer',
            'is_sample' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Confirmed by its own member state, and by whom.
     *
     * is_sample says whether a record is demonstration data. This says who
     * vouched for it: for a record a national regulator will be asked to
     * stand behind, "confirmed" without "by whom" is not a confirmation.
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
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
