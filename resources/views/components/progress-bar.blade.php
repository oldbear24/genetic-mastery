@props(['value', 'max', 'label', 'height' => 10])
@php($pct = $max > 0 ? round(min(1, max(0, $value / $max)) * 100, 2) : 0)
{{-- SVG keeps the width out of inline styles, so the CSP needs no 'unsafe-inline'. --}}
<div role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}" {{ $attributes->merge(['class' => 'w-full']) }}>
    <svg class="block w-full" height="{{ $height }}" aria-hidden="true" focusable="false">
        <rect x="0" y="0" width="100%" height="{{ $height }}" rx="{{ $height / 2 }}" class="fill-line-head"/>
        @if ($pct > 0)
            <rect x="0" y="0" width="{{ $pct }}%" height="{{ $height }}" rx="{{ $height / 2 }}" class="fill-ok"/>
        @endif
    </svg>
</div>
