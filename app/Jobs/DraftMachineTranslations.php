<?php

namespace App\Jobs;

use App\Services\MachineTranslation\MachineDrafts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drafts the other languages of an approved item (item 1b). Queued, because
 * a translation service can take seconds per field and the moderator should
 * not wait for it. A record deleted before the job runs is skipped.
 */
class DraftMachineTranslations implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public string $type,
        public int $id,
        public ?int $approvedBy = null,
    ) {}

    public function handle(): void
    {
        $class = Relation::getMorphedModel($this->type) ?? $this->type;

        if (! class_exists($class)) {
            return;
        }

        $record = $class::query()->find($this->id);

        if ($record === null) {
            return;
        }

        try {
            MachineDrafts::draft($record, $this->approvedBy);
        } catch (Throwable $e) {
            Log::warning('Machine drafts not written.', ['type' => $this->type, 'id' => $this->id, 'error' => $e->getMessage()]);
        }
    }
}
