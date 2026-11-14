@props(['at' => null, 'relative' => false])
@if ($at)
    @if ($relative)
        <time datetime="{{ $at->toIso8601ZuluString() }}" title="{{ \App\Support\Format::datetime($at) }}" data-local="title" {{ $attributes }}>{{ \App\Support\Format::relative($at) }}</time>
    @else
        <time datetime="{{ $at->toIso8601ZuluString() }}" data-local {{ $attributes }}>{{ \App\Support\Format::datetime($at) }}</time>
    @endif
@endif
