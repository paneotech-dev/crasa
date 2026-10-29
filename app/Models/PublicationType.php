<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\IsTaxonomyTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of publication in the library: annual report, guideline, study,
 * position paper, communique, minutes, and whatever the Secretariat adds
 * under Content > Taxonomies. Replaced the App\Enums\PublicationType enum
 * on 29 October 2026; the migration seeded the enum's values unchanged.
 */
class PublicationType extends Model
{
    use Auditable, HasTranslations, IsTaxonomyTerm;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['slug', 'sort_order'];

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'publication_type', 'slug');
    }
}
