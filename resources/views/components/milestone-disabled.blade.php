{{-- A stat switched off for this species by an admin: no claims, does not count. --}}
@props(['milestone'])
<span class="cell-disabled" role="img" aria-label="{{ __('milestones.cell.aria_disabled', ['milestone' => $milestone->title()]) }}">
    <span aria-hidden="true" class="font-mono">—</span>
    <span class="text-[13px]">{{ __('milestones.cell.disabled') }}</span>
</span>
