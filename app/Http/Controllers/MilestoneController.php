<?php

namespace App\Http\Controllers;

use App\Models\DiscordOutbox;
use App\Models\Milestone;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    /**
     * Milestone detail. Public: state, discoverer, dates, verifying admin,
     * evidence and public notes of the verified submission (HIS-1). Admins
     * also see earlier attempts (HIS-2). Nobody else learns who has a
     * pending claim (HP-4).
     */
    public function show(Request $request, Milestone $milestone): View
    {
        $isAdmin = $request->user()?->isAdmin() ?? false;

        $milestone->load([
            'verifiedSubmission.user',
            'verifiedSubmission.reviewer',
            'verifiedSubmission.images',
            'verifiedSubmission.notes' => fn ($q) => $isAdmin ? $q->with('author') : $q->where('visibility', 'public')->with('author'),
        ]);
        $milestone->loadExists(['pendingSubmissions as has_pending']);
        $verified = $milestone->verifiedSubmission;

        $earlier = $isAdmin
            ? Submission::query()
                ->where('milestone_id', $milestone->id)
                ->when($verified, fn ($q) => $q->whereKeyNot($verified->id))
                ->with(['user', 'reviewer', 'revoker'])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('milestones.show', [
            'milestone' => $milestone,
            'state' => $milestone->state(),
            'verified' => $verified,
            'announced' => $verified !== null && DiscordOutbox::query()
                ->where('submission_id', $verified->id)
                ->where('kind', DiscordOutbox::KIND_VERIFIED)
                ->where('status', DiscordOutbox::STATUS_SENT)
                ->exists(),
            'earlier' => $earlier,
            'isAdmin' => $isAdmin,
        ]);
    }
}
