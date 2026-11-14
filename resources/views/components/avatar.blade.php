@props(['user', 'size' => 36])
@php($sizeClass = match (true) { $size >= 44 => 'size-11 text-base', $size >= 36 => 'size-9 text-sm', default => 'size-[34px] text-sm' })
@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl(128) }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="lazy" {{ $attributes->merge(['class' => "$sizeClass shrink-0 rounded-full bg-line object-cover"]) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => "$sizeClass flex shrink-0 items-center justify-center rounded-full bg-line font-semibold text-ink"]) }}>{{ $user->initial() }}</span>
@endif
