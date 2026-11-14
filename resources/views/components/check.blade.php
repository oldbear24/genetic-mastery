{{-- One admin check line: PARALLEL, LATER CLAIM, DUPLICATE, OK (ADM-3, ADM-4). --}}
@props(['tag', 'tone' => 'ok'])
@php
    $box = match ($tone) {
        'wait' => 'border border-dashed border-wait/70 bg-wait/6',
        'bad' => 'border border-bad/60 bg-bad/6',
        default => 'border border-line bg-sunken',
    };
    $tagColor = match ($tone) {
        'wait' => 'text-wait-text',
        'bad' => 'text-bad-text',
        default => 'text-ok-text',
    };
@endphp
<div class="flex items-start gap-2.5 rounded-[10px] px-3.5 py-2.5 {{ $box }}">
    <span class="pt-0.5 text-xs font-bold tracking-[0.06em] whitespace-nowrap {{ $tagColor }}">{{ $tag }}</span>
    <span class="text-sm">{{ $slot }}</span>
</div>
