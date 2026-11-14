<x-layout :title="__('app.nav.dashboard')" current="dashboard" class="flex flex-col gap-8">
    <h1 class="m-0 font-display text-[30px] font-semibold">{{ __('dashboard.welcome', ['name' => $user->display_name]) }}</h1>

    <section class="grid gap-4 sm:grid-cols-2">
        <x-counter-card size="sm" :label="__('app.counter.genetic_mastery')" :value="$summary['verified']" :max="$summary['total']" :bar-label="__('app.counter.progress_aria')" />
        <x-counter-card size="sm" :label="__('app.counter.indo_potential')" :value="$summary['indo']" :max="$summary['indo_max']" :bar-label="__('app.counter.indo_aria')" />
    </section>
</x-layout>
