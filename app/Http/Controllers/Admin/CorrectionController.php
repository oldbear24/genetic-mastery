<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\SubmissionNote;
use App\Models\User;
use App\Services\ClusterProgress;
use App\Services\CorrectionService;
use App\Services\DecisionRefused;
use App\Services\Discord\OutboxSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Corrections after a decision (OPR-2 to OPR-5). */
class CorrectionController extends Controller
{
    public function __construct(private readonly CorrectionService $corrections) {}

    public function revoke(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('correct', $submission);

        $data = $request->validate(
            ['revoke_reason' => ['required', 'string', 'min:3', 'max:1000']],
            ['revoke_reason.required' => __('corrections.errors.reason_required')],
        );

        return $this->attempt(function () use ($request, $submission, $data) {
            $result = $this->corrections->revoke($request->user(), $submission, $data['revoke_reason']);
            OutboxSender::sendAfterResponse();

            return __('corrections.done.revoked', [
                'id' => $submission->public_id,
                'verified' => $result['verified'],
                'total' => app(ClusterProgress::class)->totalMilestones(),
                'indo' => $result['indo'],
            ]).($result['reopened'] > 0 ? ' '.trans_choice('corrections.done.reopened_count', $result['reopened']) : '');
        });
    }

    public function reopen(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('correct', $submission);

        return $this->attempt(function () use ($request, $submission) {
            $this->corrections->reopen($request->user(), $submission);

            return __('corrections.done.reopened', ['id' => $submission->public_id]);
        });
    }

    public function changeVerifier(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('correct', $submission);

        $data = $request->validate([
            'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_admin', true)],
        ]);

        return $this->attempt(function () use ($request, $submission, $data) {
            $verifier = User::query()->findOrFail($data['reviewer_id']);
            $this->corrections->changeVerifier($request->user(), $submission, $verifier);

            return __('corrections.done.verifier_changed', ['id' => $submission->public_id, 'name' => $verifier->display_name]);
        });
    }

    public function addNote(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('addNote', $submission);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in([SubmissionNote::PUBLIC, SubmissionNote::INTERNAL])],
        ]);

        $this->corrections->addNote($request->user(), $submission, $data['visibility'], $data['body']);

        return back()->with('status', __('corrections.done.note_added'));
    }

    /** @param callable(): string $action */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return back()->with('status', $action());
        } catch (DecisionRefused $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
