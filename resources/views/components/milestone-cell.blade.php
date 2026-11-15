{{-- Public cell: zámek / přesýpací hodiny / fajfka se jménem objevitele (HP-2). --}}
@props(['milestone'])
@php
    $state = $milestone->state();
    $verified = $milestone->verifiedSubmission;
@endphp
@if (! $milestone->isActive())
    <x-milestone-disabled :milestone="$milestone" />
@elseif ($state === \App\Enums\MilestoneState::Verified)
    <a href="{{ route('milestones.show', $milestone) }}" class="cell-verified" aria-label="{{ __('milestones.cell.aria_verified', ['milestone' => $milestone->title(), 'name' => $verified->user->display_name]) }}">
        <x-icon.check />
        <span class="flex min-w-0 flex-col leading-tight">
            <span class="text-[10.5px] font-semibold tracking-[0.08em] text-ok-text">{{ __('milestones.cell.verified') }}</span>
            <span class="truncate text-[13px] font-medium">{{ $verified->user->display_name }}</span>
        </span>
    </a>
@elseif ($state === \App\Enums\MilestoneState::Pending)
    <a href="{{ route('milestones.show', $milestone) }}" class="cell-pending" aria-label="{{ __('milestones.cell.aria_pending', ['milestone' => $milestone->title()]) }}">
        <x-icon.hourglass />
        <span class="flex min-w-0 flex-col leading-tight">
            <span class="text-[10.5px] font-semibold tracking-[0.08em]">{{ __('milestones.cell.pending') }}</span>
            <span class="truncate text-[13px] text-wait-dim">{{ __('milestones.cell.pending_sub') }}</span>
        </span>
    </a>
@else
    <a href="{{ route('milestones.show', $milestone) }}" class="cell-locked" aria-label="{{ __('milestones.cell.aria_locked', ['milestone' => $milestone->title()]) }}">
        <x-icon.lock />
        <span class="text-[13px]">{{ __('milestones.cell.locked') }}</span>
    </a>
@endif
