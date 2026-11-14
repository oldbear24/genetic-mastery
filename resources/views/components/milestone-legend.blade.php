<ul aria-label="{{ __('milestones.grid.legend') }}" class="m-0 flex list-none flex-wrap gap-[18px] p-0 text-[13px] text-ink-2">
    <li class="flex items-center gap-1.5"><x-icon.check />{{ __('milestones.state.verified') }}</li>
    <li class="flex items-center gap-1.5"><x-icon.hourglass />{{ __('milestones.state.pending') }}</li>
    <li class="flex items-center gap-1.5"><x-icon.lock />{{ __('milestones.state.locked') }}</li>
</ul>
