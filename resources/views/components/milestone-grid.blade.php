{{--
    Species in rows, stats in columns. A real table on wider screens; on
    phones every species becomes a card with its six stats (NF-8).
    The cell slot lets the dashboard reuse the grid with its own cells.
--}}
@props(['grid', 'stats', 'cell' => null, 'showCounts' => true])

<div class="hidden overflow-x-auto rounded-2xl border border-line bg-surface md:block">
    <table class="w-full min-w-[980px] table-fixed border-separate border-spacing-0">
        <caption class="sr-only">{{ __('milestones.grid.caption') }}</caption>
        <colgroup>
            <col class="w-[212px]">
            @foreach ($stats as $stat)
                <col>
            @endforeach
        </colgroup>
        <thead>
            <tr class="text-left text-xs font-semibold tracking-[0.08em] text-ink-label uppercase">
                <th scope="col" class="border-b border-line py-3.5 pr-1 pl-4 font-semibold">{{ __('milestones.grid.species') }}</th>
                @foreach ($stats as $statName)
                    <th scope="col" class="border-b border-line px-1 py-3.5 font-semibold {{ $loop->last ? 'pr-4' : '' }}">{{ $statName }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($grid as $row)
                <tr>
                    <th scope="row" class="relative isolate overflow-hidden py-2 pr-1 pl-4 text-left font-normal {{ $loop->last ? '' : 'border-b border-line-soft' }}">
                        <x-species-art :code="$row['code']" class="species-art-row top-1 right-2 h-[calc(100%-0.25rem)] w-[120px] opacity-60" />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-[15px] font-semibold text-ink">{{ $row['name'] }}</span>
                            @if ($showCounts)
                                <span class="font-mono text-xs text-ink-3">{{ __('milestones.grid.row_verified', ['count' => $row['verified'], 'total' => $row['milestones']->count()]) }}</span>
                            @endif
                        </span>
                    </th>
                    @foreach ($row['milestones'] as $milestone)
                        <td class="px-1 py-2 {{ $loop->last ? 'pr-4' : '' }} {{ $loop->parent->last ? '' : 'border-b border-line-soft' }}">
                            @if ($cell)
                                {{ $cell($milestone) }}
                            @else
                                <x-milestone-cell :milestone="$milestone" />
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="flex flex-col gap-3 md:hidden">
    @foreach ($grid as $row)
        <section class="card relative isolate overflow-hidden p-4" aria-label="{{ $row['name'] }}">
            <x-species-art :code="$row['code']" class="species-art-row top-1.5 right-28 h-14 w-28 opacity-50" />
            <div class="mb-3 flex items-baseline justify-between gap-2">
                <h3 class="m-0 text-[15px] font-semibold">{{ $row['name'] }}</h3>
                @if ($showCounts)
                    <span class="font-mono text-xs text-ink-3">{{ __('milestones.grid.row_verified', ['count' => $row['verified'], 'total' => $row['milestones']->count()]) }}</span>
                @endif
            </div>
            <ul class="m-0 grid list-none grid-cols-2 gap-2 p-0">
                @foreach ($row['milestones'] as $milestone)
                    <li class="flex flex-col gap-1">
                        <span class="text-xs font-semibold tracking-[0.08em] text-ink-label uppercase">{{ $milestone->statName() }}</span>
                        @if ($cell)
                            {{ $cell($milestone) }}
                        @else
                            <x-milestone-cell :milestone="$milestone" />
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
