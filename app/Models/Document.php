<?php

namespace App\Models;

use App\Enums\Clearance;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file attached to an instrument, a publication or a committee.
 *
 * Stored on the private disk, outside the web root, and only ever reached
 * through DocumentController, which checks role, country scope and clearance
 * before streaming a byte.
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use Auditable, HasFactory, MarksSampleData, SoftDeletes;

    protected $fillable = [
        'documentable_type', 'documentable_id', 'file_path', 'original_filename',
        'mime_type', 'size_bytes', 'locale', 'thumbnail_path', 'clearance',
        'committee_id', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'clearance' => Clearance::class,
            'size_bytes' => 'integer',
            'download_count' => 'integer',
            'is_sample' => 'boolean',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('clearance', Clearance::Public->value);
    }

    public function isPublic(): bool
    {
        return $this->clearance === Clearance::Public;
    }

    /**
     * The member state this document belongs to, taken from whatever it is
     * attached to. Null means regional.
     */
    public function owningCountryId(): ?int
    {
        $owner = $this->relationLoaded('documentable')
            ? $this->getRelation('documentable')
            : $this->documentable()->first();

        return $owner?->country_id ?? null;
    }

    /**
     * The committee whose seats answer who may open this at Confidential.
     * Falls back to the committee the document is attached to.
     */
    public function owningCommitteeId(): ?int
    {
        if ($this->committee_id !== null) {
            return $this->committee_id;
        }

        $owner = $this->relationLoaded('documentable')
            ? $this->getRelation('documentable')
            : $this->documentable()->first();

        return $owner instanceof Committee ? $owner->getKey() : null;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** A readable size for the document row. */
    public function readableSize(): string
    {
        $bytes = $this->size_bytes;

        return match (true) {
            $bytes >= 1_048_576 => round($bytes / 1_048_576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' bytes',
        };
    }
}
