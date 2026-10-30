<?php

namespace App\Events;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A moderator approved an item and it is now public. Dispatched by
 * App\Support\Approvals::approve after the change is committed.
 */
class ContentApproved
{
    use Dispatchable;

    public function __construct(
        public readonly Model $item,
        public readonly Approval $approval,
        public readonly ?int $approvedBy = null,
    ) {}
}
