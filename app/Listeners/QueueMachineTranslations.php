<?php

namespace App\Listeners;

use App\Events\ContentApproved;
use App\Jobs\DraftMachineTranslations;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * On approval, queue the machine drafts of the other languages. If the job
 * cannot be queued (no jobs table, queue store down) it runs here and now,
 * so an approval is never left without its drafts and never fails because
 * of them.
 */
class QueueMachineTranslations
{
    public function handle(ContentApproved $event): void
    {
        $job = new DraftMachineTranslations($event->item->getMorphClass(), (int) $event->item->getKey(), $event->approvedBy);

        try {
            dispatch($job);
        } catch (Throwable $e) {
            Log::notice('Machine drafts run without the queue.', ['error' => $e->getMessage()]);
            dispatch_sync($job);
        }
    }
}
