{{-- One own submission in the dashboard and in "Moje žádosti" (HR-4, HR-5). --}}
@props(['submission', 'otherPending' => 0, 'claimable' => true])
@php
    $s = $submission;
    $S = \App\Enums\SubmissionStatus::class;
    $reviewer = $s->reviewer?->display_name ?? __('app.none');
@endphp
<li class="grid grid-cols-1 items-center gap-x-4 gap-y-2 border-t border-line-soft py-3.5 sm:grid-cols-[112px_minmax(0,1fr)_auto_150px]">
    <a href="{{ route('submissions.show', $s) }}" class="font-mono text-[13px] text-ink-2 no-underline hover:text-ink">{{ $s->public_id }}</a>
    <span class="flex min-w-0 flex-col gap-0.5">
        <span class="font-semibold">{{ $s->milestone->title() }}</span>
        <span class="text-[13px] text-ink-3">
            @if ($s->status === $S::Verified)
                {{ __('submissions.row.verified', ['date' => \App\Support\Format::datetime($s->reviewed_at), 'admin' => $reviewer]) }}
            @elseif ($s->status === $S::Rejected)
                {{ __('submissions.row.submitted_reviewed', ['date' => \App\Support\Format::datetime($s->submitted_at), 'admin' => $reviewer]) }}
            @elseif ($s->status === $S::Withdrawn)
                {{ __('submissions.row.withdrawn', ['date' => \App\Support\Format::datetime($s->withdrawn_at)]) }}
            @elseif ($s->status === $S::Revoked)
                {{ __('submissions.row.revoked', ['date' => \App\Support\Format::datetime($s->revoked_at)]) }}
            @elseif ($s->status === $S::Superseded)
                {{ __('submissions.row.superseded', ['date' => \App\Support\Format::datetime($s->updated_at)]) }}
            @else
                {{ __('submissions.row.submitted', ['date' => \App\Support\Format::datetime($s->submitted_at)]) }}
            @endif
        </span>
        @if ($s->status === $S::Rejected && $s->rejection_reason)
            <span class="text-[13px] text-bad-text">{{ __('submissions.row.reason', ['reason' => $s->rejection_reason]) }}</span>
        @elseif ($s->status === $S::Revoked && $s->revoke_reason)
            <span class="text-[13px] text-bad-text">{{ __('submissions.row.reason', ['reason' => $s->revoke_reason]) }}</span>
        @elseif ($s->status === $S::Pending && $otherPending > 0)
            <span class="text-[13px] text-wait-dim">{{ trans_choice('submissions.show.other_pending', $otherPending) }}</span>
        @endif
    </span>
    <span><x-status-badge :status="$s->status" /></span>
    <span class="flex sm:justify-end">
        @if ($s->status === $S::Pending)
            <form method="POST" action="{{ route('submissions.withdraw', $s) }}">
                @csrf
                <button type="submit" class="btn-ghost min-h-10 px-3.5 text-sm" aria-label="{{ __('submissions.withdraw.confirm_label', ['id' => $s->public_id]) }}">{{ __('submissions.withdraw.button') }}</button>
            </form>
        @elseif ($s->status === $S::Rejected && $claimable)
            <a href="{{ route('submissions.create', ['milestone' => $s->milestone->code]) }}" class="btn-ghost min-h-10 bg-raised px-3.5 text-sm">{{ __('submissions.row.new_claim') }}</a>
        @elseif ($s->status === $S::Verified)
            <a href="{{ route('milestones.show', $s->milestone) }}" class="link-underline inline-flex min-h-10 items-center px-1.5 text-sm">{{ __('submissions.row.view_history') }}</a>
        @else
            <a href="{{ route('submissions.show', $s) }}" class="link-underline inline-flex min-h-10 items-center px-1.5 text-sm">{{ __('submissions.row.detail') }}</a>
        @endif
    </span>
</li>
