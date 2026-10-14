<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalisedNames;
use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the three regulated sectors. Fixed set of three.
 */
class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use Auditable, HasFactory, HasLocalisedNames;

    /** French and Portuguese names: lang/{fr,pt}/names.php. */
    protected function localisedNames(): array
    {
        return ['group' => 'sectors', 'key' => 'slug', 'fields' => ['name']];
    }

    public const TELECOMMUNICATIONS = 'telecommunications';

    public const BROADCASTING = 'broadcasting';

    public const POSTAL = 'postal';

    /** @var list<string> */
    public const SLUGS = [
        self::TELECOMMUNICATIONS,
        self::BROADCASTING,
        self::POSTAL,
    ];

    protected $fillable = ['slug', 'name', 'icon', 'accent_token'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    /** The regulators holding a mandate in this sector. */
    public function authorities(): BelongsToMany
    {
        return $this->belongsToMany(Authority::class)->withTimestamps();
    }
}
