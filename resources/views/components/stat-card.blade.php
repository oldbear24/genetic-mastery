@props(['label', 'value', 'max' => null, 'barLabel' => null])
<div class="flex flex-col gap-2.5 rounded-[14px] border border-line bg-surface px-5 py-[18px]">
    <span class="text-xs font-semibold tracking-[0.12em] text-ink-label uppercase">{{ $label }}</span>
    <span class="font-mono text-[30px] leading-none font-semibold">{{ $value }}@if ($max !== null) <span class="text-lg text-ink-3">/ {{ $max }}</span>@endif</span>
    @if ($max !== null)
        <x-progress-bar :value="(int) $value" :max="$max" :label="$barLabel" :height="6" />
    @endif
    @if ($slot->isNotEmpty())
        <span class="text-[13px] text-ink-2">{{ $slot }}</span>
    @endif
</div>
