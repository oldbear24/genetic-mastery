<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    /**
     * Public milestone detail (HP-3, HIS-1). Shows who has a pending claim
     * to nobody but admins and the claim authors (HP-4).
     */
    public function show(Milestone $milestone): View
    {
        $milestone->load(['verifiedSubmission.user', 'verifiedSubmission.reviewer', 'verifiedSubmission.images']);
        $milestone->loadExists(['pendingSubmissions as has_pending']);

        return view('milestones.show', [
            'milestone' => $milestone,
            'state' => $milestone->state(),
            'verified' => $milestone->verifiedSubmission,
        ]);
    }
}
