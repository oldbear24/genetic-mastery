<x-layout :title="__('app.nav.dashboard')" current="dashboard" class="flex flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="m-0 font-display text-[30px] font-semibold">{{ __('dashboard.welcome', ['name' => $user->display_name]) }}</h1>
            <p class="m-0 text-ink-2">
                @if ($pendingCount === 0 && $rejectedCount === 0)
                    {{ __('dashboard.summary_none') }}
                @else
                    @if ($pendingCount > 0){{ trans_choice('dashboard.summary_pending', $pendingCount) }}@endif
                    @if ($rejectedCount > 0){{ trans_choice('dashboard.summary_rejected', $rejectedCount) }}@endif
                @endif
            </p>
        </div>
        <a href="{{ route('submissions.create') }}" class="btn-primary min-h-12 rounded-[10px] px-5">
            <x-icon.plus :size="18" />{{ __('dashboard.new_claim') }}
        </a>
    </div>

    {{-- HR-1, HR-6 --}}
    <section aria-label="{{ __('app.counter.progress_aria') }}" class="grid grid-cols-[repeat(auto-fit,minmax(min(250px,100%),1fr))] gap-4">
        <x-stat-card :label="__('app.counter.genetic_mastery')" :value="$summary['verified']" :max="$summary['total']" :bar-label="__('app.counter.progress_aria')" />
        <x-stat-card :label="__('app.counter.indo_potential')" :value="$summary['indo']" :max="$summary['indo_max']" :bar-label="__('app.counter.indo_aria')" />
        <x-stat-card :label="__('dashboard.stats.discoveries')" :value="$standing['discoveries'] ?? 0">
            {{ __('dashboard.stats.of_verified', ['count' => $summary['verified']]) }}
        </x-stat-card>
        <x-stat-card :label="__('dashboard.stats.leaderboard')" :value="$standing ? '#'.$standing['rank'] : '—'">
            @if ($standing === null)
                {{ __('dashboard.stats.no_rank') }}
            @elseif ($standing['tied_with'] > 0)
                {{ __('dashboard.stats.tied', ['count' => $standing['discoveries']]) }}
            @else
                {{ __('dashboard.stats.leading') }}
            @endif
        </x-stat-card>
    </section>

    {{-- HR-4, HR-5 --}}
    <section aria-labelledby="subs-title" class="card flex flex-col gap-1.5 px-6 py-[22px]">
        <div class="mb-1.5 flex items-baseline justify-between gap-3">
            <h2 id="subs-title" class="m-0 font-display text-xl font-semibold">{{ __('dashboard.submissions.title') }}</h2>
            @if ($totalSubmissions > $recent->count())
                <a href="{{ route('submissions.index') }}" class="link-underline text-sm">{{ __('dashboard.submissions.show_all', ['count' => $totalSubmissions]) }}</a>
            @endif
        </div>
        @if ($recent->isEmpty())
            <p class="m-0 border-t border-line-soft pt-3.5 text-ink-3">{{ __('dashboard.submissions.empty') }}</p>
        @else
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($recent as $submission)
                    <x-submission-row :submission="$submission" :other-pending="$otherPending[$submission->milestone_id] ?? 0" :claimable="$board->claimable($submission->milestone)" />
                @endforeach
            </ul>
        @endif
    </section>

    {{-- HR-2, HR-3 --}}
    <section aria-labelledby="mine-title" class="flex flex-col gap-3.5">
        <div class="flex flex-col gap-1">
            <h2 id="mine-title" class="m-0 font-display text-xl font-semibold">{{ __('dashboard.grid.title') }}</h2>
            <p class="m-0 text-sm text-ink-2">{{ __('dashboard.grid.subtitle') }}</p>
        </div>
        <x-milestone-grid :grid="$grid" :stats="config('genetic.stats')" :show-counts="false" :cell="fn ($milestone) => view('components.player-cell', ['milestone' => $milestone, 'board' => $board])" />
    </section>
</x-layout>
