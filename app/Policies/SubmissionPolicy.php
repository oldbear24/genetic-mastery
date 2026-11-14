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

    /** Originals are for admins only (UPL-5, OPR-6). */
    public function downloadOriginal(User $user, Submission $submission): bool
    {
        return $user->isAdmin();
    }
}
