<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;
use App\Support\Approvals;

/**
 * One approval request. Deciding needs the approve permission for the
 * item's own type (approve publications, news, events, pages or social
 * posts). The four-eyes rule is enforced in Approvals, where it carries its
 * own message.
 */
class ApprovalPolicy
{
    public function view(User $user, Approval $approval): bool
    {
        $type = Approvals::typeOfApproval($approval);

        return $approval->submitted_by === $user->id
            || ($type !== null && Approvals::mayApproveType($user, $type))
            || Approvals::mayWithdraw($user, $approval);
    }

    public function decide(User $user, Approval $approval): bool
    {
        $type = Approvals::typeOfApproval($approval);

        return $type !== null && Approvals::mayApproveType($user, $type);
    }

    public function withdraw(User $user, Approval $approval): bool
    {
        return Approvals::mayWithdraw($user, $approval);
    }
}
