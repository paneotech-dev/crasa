<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a file in a folder. Replacing a file adds a version and
 * keeps the earlier ones, each with who uploaded it and when.
 *
 * A version is reached only through its document: the version download
 * checks the document policy on the file it belongs to, so a version id is
 * no way round a folder's access.
 */
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id', 'version', 'file_path', 'original_filename', 'mime_type', 'size_bytes', 'uploaded_by', 'created_at',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'size_bytes' => 'integer', 'created_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
