<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One stored image.
 *
 * Nothing on the public site points at the original. Screen 14: "The
 * original is kept and never served directly." The originals here are
 * archive masters up to 20 MB, and serving one to a reader on a mobile
 * connection in Southern Africa would be indefensible on a platform for
 * this audience.
 */
class MediaAsset extends Model
{
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /**
     * Alt text and caption are per-language. The credit line is not: a
     * photographer's name is the same in three languages.
     *
     * @var list<string>
     */
    protected array $translatable = ['alt', 'caption'];

    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
        'width', 'height', 'checksum', 'renditions',
        'rights_holder', 'licence', 'source_url',
        'rights_confirmed_at', 'rights_confirmed_by', 'uploaded_by', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'renditions' => 'array',
            'rights_confirmed_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rightsConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rights_confirmed_by');
    }

    /** The stable reference a block stores in its settings. */
    public function reference(): string
    {
        return 'media:'.$this->id;
    }

    /**
     * The URL for a rendition, or the nearest one built.
     *
     * Never the original. If nothing has been generated the asset is not
     * servable and this returns null, so a block renders its designed
     * absence rather than a broken image.
     */
    public function url(int $width = 960): ?string
    {
        $renditions = $this->renditions ?? [];

        if ($renditions === []) {
            return null;
        }

        $widths = array_map('intval', array_keys($renditions));
        sort($widths);

        $chosen = $widths[count($widths) - 1];

        foreach ($widths as $candidate) {
            if ($candidate >= $width) {
                $chosen = $candidate;
                break;
            }
        }

        return Storage::disk($this->disk)->url($renditions[(string) $chosen]['path']);
    }

    /**
     * Whether this asset may appear on a given language's pages.
     *
     * Screen 14 draws the Portuguese alt text field as "Required. This
     * asset cannot appear on the Portuguese site until it is filled." That
     * is a rule, not a warning, and this is where it is answered.
     */
    public function mayAppearIn(string $locale): bool
    {
        // Deliberately not the resolved value. Falling back to the
        // English here would let an asset onto the Portuguese site with
        // English alt text, which is exactly the failure the rule exists
        // to stop.
        return trim((string) ($this->translationValuesFor('alt')[$locale] ?? '')) !== '';
    }

    /** @return list<string> */
    public function localesWithAlt(): array
    {
        return array_values(array_filter(
            Locales::SUPPORTED,
            fn (string $locale) => $this->mayAppearIn($locale)
        ));
    }

    public function hasCompleteAlt(): bool
    {
        return count($this->localesWithAlt()) === count(Locales::SUPPORTED);
    }

    /** Assets missing alt text in at least one language. */
    public function scopeMissingAlt(Builder $query): Builder
    {
        return $query->whereRaw(
            '(select count(distinct locale) from translations
              where translations.translatable_type = ?
                and translations.translatable_id = media_assets.id
                and translations.field = ?
                and trim(translations.value) <> ?) < ?',
            [self::class, 'alt', '', count(Locales::SUPPORTED)]
        );
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    public function dimensions(): ?string
    {
        return $this->width && $this->height ? $this->width.' by '.$this->height : null;
    }
}
