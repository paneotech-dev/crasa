<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A CRASA committee. Cross-sector committees have no sector.
 */
class Committee extends Model
{
    /** @use HasFactory<CommitteeFactory> */
    use Auditable, HasFactory;

    use MarksSampleData;

    /** @var list<string> */
    public const ROLES = ['member', 'chair', 'observer'];

    protected $fillable = [
        'slug',
        'name',
        'sector_id',
        'mandate',
        'chair_authority_id',
        'is_active',
        'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function chairAuthority(): BelongsTo
    {
        return $this->belongsTo(Authority::class, 'chair_authority_id');
    }

    /** The people who hold seats on this committee. Ruling 2. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_sample'])
            ->withTimestamps();
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class)
            ->withPivot(['role', 'is_sample'])
            ->withTimestamps();
    }

    public function isCrossSector(): bool
    {
        return $this->sector_id === null;
    }
}
