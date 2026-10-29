<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CRASA project. WS-12's no-content-loss clause.
 *
 * Current and past are the same records in two states, not two tables. A
 * project that finishes keeps its page, its documents and its URL; only
 * `state` changes. That is what the legacy site's two menu sections
 * actually are, and modelling them as separate things would break the
 * permalink on the day a project ends.
 */
class Project extends Model
{
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'summary', 'body', 'milestones'];

    protected $fillable = [
        'slug', 'state', 'sector_id', 'partner', 'starts_on', 'ends_on',
        'programme', 'funding_partner', 'countries', 'lead_committee_id',
        'hero_image_path', 'status', 'published_at', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        // Published from the back office without a date: published now, so
        // the public scope (published_at in the past) shows it.
        static::saving(function (Project $project) {
            if ($project->status === 'published' && $project->published_at === null) {
                $project->published_at = now();
            }
        });
    }

    public function leadCommittee(): BelongsTo
    {
        return $this->belongsTo(Committee::class, 'lead_committee_id');
    }

    /** @return list<string> */
    public function countryList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', (string) $this->countries))));
    }

    /**
     * The milestones in the reader's language, in the order written. One per
     * line in the back office: "March 2026 | Validation workshop" or
     * "March 2026: Validation workshop"; a line without a date is a step
     * on its own.
     *
     * @return list<array{when: ?string, label: string, lang: ?string}>
     */
    public function milestoneList(): array
    {
        $text = $this->translate('milestones');

        if ($text === null || trim($text->value) === '') {
            return [];
        }

        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $text->value) as $line) {
            $line = trim(preg_replace('/^[-*\x{2022}]\s*/u', '', trim($line)));

            if ($line === '') {
                continue;
            }

            $when = null;
            $label = $line;

            if (str_contains($line, '|')) {
                [$when, $label] = array_map('trim', explode('|', $line, 2));
            } elseif (preg_match('/^([^:]{1,30}):\s+(.+)$/u', $line, $m)) {
                [$when, $label] = [trim($m[1]), trim($m[2])];
            }

            if ($label === '') {
                [$label, $when] = [(string) $when, null];
            }

            $out[] = ['when' => $when !== '' ? $when : null, 'label' => $label, 'lang' => $text->isFallback() ? $text->locale : null];
        }

        return $out;
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function legacyRecord(): MorphOne
    {
        return $this->morphOne(LegacyRecord::class, 'recordable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isCurrent(): bool
    {
        return $this->state === 'current';
    }
}
