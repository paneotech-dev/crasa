<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored regional brief. RP-12.
 *
 * It holds what the brief is about, not what it said. Reopening one shows
 * today's figures, which is what anyone reopening it wants; the exported
 * PDF beside it is the copy that was circulated, and the difference
 * between the two is the point.
 */
class Report extends Model
{
    use Auditable, HasFactory, MarksSampleData;

    public const COUNTRY = 'country';

    public const INDICATOR = 'indicator';

    protected $fillable = [
        'subject_type', 'subject_key', 'title', 'period', 'locale',
        'generated_by', 'pdf_path', 'exported_at', 'is_sample',
    ];

    protected function casts(): array
    {
        return ['exported_at' => 'datetime', 'is_sample' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isAboutCountry(): bool
    {
        return $this->subject_type === self::COUNTRY;
    }

    public function country(): ?Country
    {
        return $this->isAboutCountry()
            ? Country::where('iso_code', $this->subject_key)->first()
            : null;
    }

    public function indicator(): ?Indicator
    {
        return $this->isAboutCountry()
            ? null
            : Indicator::where('slug', $this->subject_key)->first();
    }
}
