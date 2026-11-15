<x-layout :title="__('app.nav.milestones')" current="milestones" glow class="flex flex-col gap-10">
    {{-- HP-1: counters --}}
    <section aria-label="{{ __('app.counter.progress_aria') }}" class="grid gap-6 md:grid-cols-2">
        <x-counter-card :label="__('app.counter.genetic_mastery')" :value="$summary['verified']" :max="$summary['total']" :bar-label="__('app.counter.progress_aria')" :image="asset('images/genetic-mastery.webp')">
            {{ __('app.counter.verified_across', ['percent' => \App\Support\Format::percent($summary['percent'])]) }}
        </x-counter-card>
        <x-counter-card :label="__('app.counter.indo_potential')" :value="$summary['indo']" :max="$summary['indo_max']" :bar-label="__('app.counter.indo_aria')" :image="asset('images/indo-potential.webp')">
            {{ __('app.counter.indo_formula', ['base' => config('genetic.indo.base'), 'per' => config('genetic.indo.per_milestone')]) }}
        </x-counter-card>
    </section>

    {{-- HP-2: 9 × 6 grid --}}
    <section aria-labelledby="milestones-title" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 id="milestones-title" class="m-0 font-display text-[26px] font-semibold">{{ __('milestones.grid.title') }}</h1>
                <p class="m-0 text-sm text-ink-2">{{ __('milestones.grid.subtitle', [
                    'species' => trans_choice('app.count.species', $grid->count()),
                    'stats' => trans_choice('app.count.stats', count($stats)),
                    'points' => trans_choice('app.count.points', config('genetic.required_value')),
                ]) }}</p>
            </div>
            <x-milestone-legend />
        </div>

        <x-milestone-grid :grid="$grid" :stats="$stats" />
    </section>

    {{-- HP-5: recent discoveries + leaderboard --}}
    <section class="grid gap-6 md:grid-cols-2">
        <div class="card flex flex-col gap-2 px-7 py-6">
            <h2 class="m-0 mb-1 font-display text-xl font-semibold">{{ __('milestones.recent.title') }}</h2>
            @if ($recent->isEmpty())
                <p class="m-0 border-t border-line-soft py-3 text-sm text-ink-3">{{ __('milestones.recent.empty') }}</p>
            @else
                <ol class="m-0 flex list-none flex-col p-0">
                    @foreach ($recent as $submission)
                        <li class="flex items-center justify-between gap-3 border-t border-line-soft py-3">
                            <a href="{{ route('milestones.show', $submission->milestone) }}" class="flex min-w-0 flex-col gap-0.5 text-ink no-underline hover:text-ink">
                                <span class="font-semibold">{{ $submission->milestone->title() }}</span>
                                <span class="text-[13px] text-ink-2">{{ __('milestones.recent.discoverer', ['name' => $submission->user->display_name]) }}</span>
                            </a>
                            <span class="text-[13px] whitespace-nowrap text-ink-3"><x-time :at="$submission->reviewed_at" relative /></span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div id="leaderboard" class="card flex flex-col gap-2 px-7 py-6">
            <div class="mb-1 flex items-baseline justify-between gap-3">
                <h2 class="m-0 font-display text-xl font-semibold">{{ __('milestones.leaders.title') }}</h2>
                <a href="{{ route('leaderboard') }}" class="link-underline text-sm">{{ __('milestones.leaders.full') }}</a>
            </div>
            @if ($leaders->isEmpty())
                <p class="m-0 border-t border-line-soft py-3 text-sm text-ink-3">{{ __('milestones.leaders.empty') }}</p>
            @else
                <ol class="m-0 flex list-none flex-col p-0">
                    @foreach ($leaders as $row)
                        <li class="grid grid-cols-[36px_36px_minmax(0,1fr)_auto] items-center gap-3 border-t border-line-soft py-2.5">
                            <span class="font-mono text-sm text-ink-3">#{{ $row['rank'] }}</span>
                            <x-avatar :user="$row['user']" />
                            <span class="truncate font-medium">{{ $row['user']->display_name }}</span>
                            <span class="font-mono text-sm">{{ $row['discoveries'] }} <span class="text-ink-3">{{ trans_choice('app.count.milestones_word', $row['discoveries']) }}</span></span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</x-layout>
