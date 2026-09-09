<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\CountryScoped;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One member state's return for one period. RP-09.
 *
 * COUNTRY-SCOPED, AS A GLOBAL SCOPE. A representative reaches their own
 * member state's submissions and no others, and that is enforced on the
 * model rather than in a controller, so it holds in a controller, a job, a
 * report, a console command and anything written later by someone who has
 * not read this comment.
 */
class Submission extends Model
{
    use Auditable, CountryScoped, HasFactory, MarksSampleData;

    protected $fillable = [
        'country_id', 'submitted_by', 'period', 'status',
        'reviewed_by', 'submitted_at', 'reviewed_at', 'review_note', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionFieldComment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->orderBy('id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /** Unresolved comments, keyed by the indicator they are against. */
    public function openComments()
    {
        return $this->comments->where('resolved', false)->keyBy('indicator_id');
    }

    /**
     * How much of the return is filled in, by category.
     *
     * Screen 06 draws this as "Progress by category" beside the form. It
     * counts a figure with no source note as incomplete, because a figure
     * nobody can attribute is not a usable figure.
     *
     * @return array<string, array{filled: int, total: int}>
     */
    public function progress(): array
    {
        $values = $this->values->keyBy('indicator_id');
        $progress = [];

        foreach (Indicator::active()->orderBy('display_order')->get() as $indicator) {
            $progress[$indicator->category] ??= ['filled' => 0, 'total' => 0];
            $progress[$indicator->category]['total']++;

            $value = $values->get($indicator->id);

            if ($value !== null && $value->isComplete()) {
                $progress[$indicator->category]['filled']++;
            }
        }

        return $progress;
    }
}
