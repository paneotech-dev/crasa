<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One version of an instrument's translatable text. Append only: a version
 * records what the text was, so rewriting one would defeat the point.
 */
class InstrumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'instrument_id', 'version_number', 'changed_by', 'change_summary', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('A version records what the text was. It cannot be rewritten.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Version history cannot be deleted.');
        });

        static::creating(function (InstrumentVersion $version): void {
            $version->created_at ??= now();
        });
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * The text of one field in one language as it stood at this version.
     */
    public function valueFor(string $field, string $locale): ?string
    {
        return $this->payload[$locale][$field] ?? null;
    }
}
