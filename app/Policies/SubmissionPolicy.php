<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;

/**
 * Who may do what to a member state's return.
 *
 * The country scope already keeps another member state's rows out of every
 * query. This is the write side of the same rule: a crafted request must
 * not be able to do what a query could not read. Both halves, because a
 * global scope protects reads and says nothing about a POST.
 */
class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SubmitCountryReturn)
            || $user->hasPermission(Permission::ValidateSubmission);
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->viewAny($user) && $user->mayActOnCountry($submission->country_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SubmitCountryReturn);
    }

    /**
     * Editing the figures.
     *
     * Only the member state, only their own return, and only while it is
     * open. The Secretariat reviews a return; it does not rewrite one, or
     * the member state's name would be on figures they did not enter.
     */
    public function update(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::SubmitCountryReturn)
            && $user->mayActOnCountry($submission->country_id)
            && $submission->isEditable();
    }

    /** Sending it. The member state's act. */
    public function submit(User $user, Submission $submission): bool
    {
        return $this->update($user, $submission);
    }

    /** Validating or returning it. The Secretariat's act, never the sender's. */
    public function review(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::ValidateSubmission)
            && $submission->status === SubmissionStatus::Submitted;
    }

    public function comment(User $user, Submission $submission): bool
    {
        return $user->hasPermission(Permission::ValidateSubmission);
    }

    public function delete(User $user, Submission $submission): bool
    {
        // A return that has been sent is a record of what a member state
        // told CRASA. It is returned or validated, never deleted.
        return $user->hasPermission(Permission::SubmitCountryReturn)
            && $user->mayActOnCountry($submission->country_id)
            && $submission->status === SubmissionStatus::Draft;
    }
}
