<?php

namespace App\Events;

use App\Models\Document;
use App\Models\Folder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A file, or a new version of a file, was added to a folder.
 *
 * Dispatched by the folder pages after the file is stored. Whoever needs to
 * tell people about it listens for this; the document pages do not.
 */
class DocumentAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly Folder $folder,
    ) {}
}
