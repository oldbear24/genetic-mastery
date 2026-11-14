@props(['entry', 'formatter'])
<ul class="m-0 flex list-none flex-col gap-0.5 p-0 text-[13px]">
    @foreach ($formatter->changes($entry) as $change)
        <li><span class="text-ink-3">{{ $change['field'] }}:</span> {{ $change['before'] }} <span aria-hidden="true">→</span><span class="sr-only">na</span> {{ $change['after'] }}</li>
    @endforeach
</ul>
