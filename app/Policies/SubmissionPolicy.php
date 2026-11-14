<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;

/**
 * Verified submissions, including their evidence, are public. Everything
 * else is visible to the author and to admins only.
 */
class SubmissionPolicy
{
    public function view(?User $user, Submission $submission): bool
    {
        if ($submission->status === SubmissionStatus::Verified) {
            return true;
        }

        return $user !== null && ($user->id === $submission->user_id || $user->isAdmin());
    }

    public function withdraw(User $user, Submission $submission): bool
    {
        return $user->id === $submission->user_id && $submission->status === SubmissionStatus::Pending;
    }

    /**
     * Approve or reject. Admins may not decide their own claims unless
     * ALLOW_SELF_APPROVE is on (ADM-7).
     */
    public function decide(User $user, Submission $submission): bool
    {
        return $user->isAdmin()
            && ($submission->user_id !== $user->id || (bool) config('genetic.allow_self_approve'));
    }

    /**
     * Revoke, reopen and change the verifier (OPR-2 to OPR-4). Same rule as
     * deciding: not on one's own claims unless self approval is allowed.
     */
    public function correct(User $user, Submission $submission): bool
    {
        return $this->decide($user, $submission);
    }

    /** Notes can be added by any admin (OPR-5). */
    public function addNote(User $user, Submission $submission): bool
    {
        return $user->isAdmin();
    }

    /** Originals are for admins only (UPL-5, OPR-6). */
    public function downloadOriginal(User $user, Submission $submission): bool
    {
        return $user->isAdmin();
    }
}
