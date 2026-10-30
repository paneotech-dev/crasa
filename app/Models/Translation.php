<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One translated field of one record in one language.
 */
class Translation extends Model
{
    protected $fillable = [
        'translatable_type', 'translatable_id', 'locale', 'field',
        'value', 'translated_by', 'is_machine_draft',
    ];

    protected function casts(): array
    {
        return [
            // Set on rows drafted by machine when an item is approved
            // (App\Services\MachineTranslation\MachineDrafts), cleared when a
            // person approves or rewrites the string.
            'is_machine_draft' => 'boolean',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translated_by');
    }
}
