<?php

// Evening pass, 29 October 2026.
return [
    // Moderation: lock while in review, withdraw, per-type approval.
    'locked_notice' => 'In review: this item is locked until a moderator approves or returns it. You can read it; changes cannot be saved.',
    'locked_refused' => 'This item is in review and locked. Wait for the moderator to approve or return it, or ask an administrator to withdraw it to Draft.',
    'withdraw_title' => 'Withdraw from review',
    'withdraw_help' => 'Takes the item back to Draft so it can be edited again. Nobody approves it; the author is told and the step is in the audit log.',
    'withdraw_comment' => 'Reason (optional)',
    'withdraw_button' => 'Withdraw to Draft',
    'withdraw_refused' => 'Only an administrator can withdraw an item from review.',
    'withdrawn_comment' => 'Withdrawn to Draft by an administrator.',
    'withdrawn_flash' => '":title" is back in Draft and can be edited again.',
    'may_not_approve_type' => 'Your role cannot approve this type of item.',
];
