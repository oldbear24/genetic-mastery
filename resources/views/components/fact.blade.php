@props(['label', 'value' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 rounded-xl border border-line bg-surface px-4 py-3.5']) }}>
    <span class="text-xs font-semibold tracking-[0.1em] text-ink-label uppercase">{{ $label }}</span>
    <span class="font-semibold">{{ $value }}</span>
    @if ($slot->isNotEmpty())
        <span class="text-[13px] text-ink-3">{{ $slot }}</span>
    @endif
</div>
