@props(['label', 'value', 'max', 'barLabel', 'size' => 'lg'])
<div {{ $attributes->merge(['class' => $size === 'lg' ? 'card flex flex-col gap-4 p-7' : 'card flex flex-col gap-3 p-5']) }}>
    <div class="{{ $size === 'lg' ? 'label-caps' : 'font-display text-xs font-semibold tracking-[0.14em] text-ink-label uppercase' }}">{{ $label }}</div>
    <div class="flex items-baseline gap-3">
        <span class="font-mono font-semibold leading-none text-ink {{ $size === 'lg' ? 'text-[56px] sm:text-[76px]' : 'text-3xl' }}">{{ $value }}</span>
        <span class="font-mono text-ink-3 {{ $size === 'lg' ? 'text-2xl sm:text-[32px]' : 'text-lg' }}">/ {{ $max }}</span>
    </div>
    <x-progress-bar :value="$value" :max="$max" :label="$barLabel" :height="$size === 'lg' ? 10 : 6" />
    @if ($slot->isNotEmpty())
        <div class="text-sm text-ink-2">{{ $slot }}</div>
    @endif
</div>
