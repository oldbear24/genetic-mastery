{{-- Dashboard cell with the player's own layer (HR-2, HR-3). --}}
@props(['milestone', 'board'])
@php
    $P = \App\Services\PlayerMilestones::class;
    $state = $board->state($milestone);
    $submission = $board->submission($milestone);
    $title = $milestone->title();
    $claimUrl = route('submissions.create', ['milestone' => $milestone->code]);
@endphp
@if ($state === $P::MINE_VERIFIED)
    <a href="{{ route('milestones.show', $milestone) }}" class="cell border-2 border-ok bg-ok/22 text-[13px] font-semibold text-ink hover:text-ink" aria-label="{{ __('dashboard.grid.aria_mine_verified', ['milestone' => $title]) }}">
        <x-icon.check />{{ __('dashboard.grid.you') }}
    </a>
@elseif ($state === $P::VERIFIED)
    <a href="{{ route('milestones.show', $milestone) }}" class="cell border border-ok/30 bg-ok/7 text-[13px] text-ink-2 hover:text-ink" aria-label="{{ __('dashboard.grid.aria_verified', ['milestone' => $title, 'name' => $submission->user->display_name]) }}">
        <x-icon.check class="text-[#5FAF8F]" /><span class="truncate">{{ $submission->user->display_name }}</span>
    </a>
@elseif ($state === $P::MINE_PENDING)
    <a href="{{ route('submissions.show', $submission) }}" class="cell border border-dashed border-wait/85 bg-wait/8 text-[13px] font-semibold text-wait-text hover:text-wait-text" aria-label="{{ __('dashboard.grid.aria_mine_pending', ['milestone' => $title]) }}">
        <x-icon.hourglass /><span class="truncate">{{ __('dashboard.grid.your_claim') }}</span>
    </a>
@elseif ($state === $P::PENDING)
    <a href="{{ $claimUrl }}" class="cell justify-between border border-dashed border-wait/60 text-[13px] text-wait-dim hover:text-wait-text" aria-label="{{ __('dashboard.grid.aria_pending', ['milestone' => $title]) }}">
        <span class="flex items-center gap-1.5"><x-icon.hourglass :size="14" />{{ __('dashboard.grid.in_review') }}</span><span class="font-semibold text-ok-text">{{ __('dashboard.grid.claim') }}</span>
    </a>
@elseif ($state === $P::MINE_REJECTED)
    <a href="{{ $claimUrl }}" class="cell justify-between border border-bad/60 text-[13px] text-bad-text hover:text-bad-text" aria-label="{{ __('dashboard.grid.aria_mine_rejected', ['milestone' => $title]) }}">
        <span class="flex items-center gap-1.5"><x-icon.x :size="14" />{{ __('dashboard.grid.rejected') }}</span><span class="font-semibold text-ok-text">{{ __('dashboard.grid.retry') }}</span>
    </a>
@else
    <a href="{{ $claimUrl }}" class="cell justify-between border border-line-head bg-sunken text-[13px] text-ink-3 hover:text-ink-2" aria-label="{{ __('dashboard.grid.aria_locked', ['milestone' => $title]) }}">
        <span class="flex items-center gap-1.5"><x-icon.lock :size="14" />{{ __('dashboard.grid.locked') }}</span><span class="font-semibold text-ok-text">{{ __('dashboard.grid.claim') }}</span>
    </a>
@endif
