{{-- Decorative render faded into its container (empty alt, not announced). Pass a Species model or an image `src`. --}}
@props(['species' => null, 'src' => null])
@php($src ??= $species?->imageUrl())
@if ($src)
    <img src="{{ $src }}" alt="" decoding="async" {{ $attributes->merge(['class' => 'species-art']) }}>
@endif
