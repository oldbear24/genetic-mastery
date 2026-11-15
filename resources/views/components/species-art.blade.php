{{-- Decorative render faded into its container (empty alt, not announced). Pass a species `code` or an image `src`. --}}
@props(['code' => null, 'src' => null])
@php($src ??= $code !== null ? \App\Support\SpeciesImage::url($code) : null)
@if ($src)
    <img src="{{ $src }}" alt="" decoding="async" {{ $attributes->merge(['class' => 'species-art']) }}>
@endif
