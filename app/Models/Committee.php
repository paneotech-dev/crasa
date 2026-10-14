<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalisedNames;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A CRASA committee. Cross-sector committees have no sector.
 */
class Committee extends Model
{
    /** @use HasFactory<CommitteeFactory> */
    use Auditable, HasFactory, HasLocalisedNames, HasTranslations;

    /** French and Portuguese names: lang/{fr,pt}/names.php. */
    protected function localisedNames(): array
    {
        return ['group' => 'committees', 'key' => 'slug', 'fields' => ['name']];
    }

    use MarksSampleData;

    /** @var list<string> */
    public const ROLES = ['member', 'chair', 'observer'];

    /**
     * The terms of reference, per language.
     *
     * mandate was a plain column until 9 September 2026. It is prose a
     * Francophone regulator has to read, and CLAUDE.md's multilingual
     * rules put body prose in the translations table.
     *
     * @var list<string>
     */
    protected array $translatable = ['mandate'];

    protected $fillable = [
        'slug',
        'name',
        'sector_id',
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

    /**
     * What this committee has published. Publications carry committee_id
     * already; the relation was never declared because nothing had asked
     * the question from this side.
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * The documents this committee owns, which is what answers who may
     * open a confidential one. See DATA_MODEL section 7: clearance is per
     * committee role, not per user.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** The meetings held under this committee. */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Has anyone told us who sits on these committees?
     *
     * The seats are one of the items CRASA has still to supply, so the
     * pivot is empty. That makes "0 member states" true of every committee
     * and "0 committees" true of every member state — fourteen identical
     * zeroes that are a fact about our data rather than about the region.
     *
     * Zero is information when it is measured. This zero is not measured,
     * it is unasked, and printing it as a figure states something the
     * platform does not know. So where nothing is recorded anywhere, the
     * count is withheld and the page says the seats are still to come.
     *
     * Not memoised. A static that outlives the request is a value that
     * goes stale the moment the Secretariat records the first seat, and
     * under a persistent worker it would stay stale until a redeploy.
     * Callers ask once per block and pass the answer into their loop, so
     * this is one EXISTS on an indexed pivot per page.
     */
    public static function seatsRecorded(): bool
    {
        return DB::table('committee_country')->exists();
    }

    public function isCrossSector(): bool
    {
        return $this->sector_id === null;
    }
}
