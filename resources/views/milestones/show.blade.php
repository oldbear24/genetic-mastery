<x-layout :title="$milestone->title()" current="milestones" class="flex flex-col gap-7">
    <nav aria-label="{{ __('app.breadcrumbs') }}" class="flex flex-wrap gap-2 text-sm text-ink-3">
        <a href="{{ route('home') }}" class="link-underline">{{ __('app.nav.milestones') }}</a>
        <span aria-hidden="true">/</span><span>{{ $milestone->dinosaurName() }}</span>
        <span aria-hidden="true">/</span><span>{{ $milestone->statName() }}</span>
    </nav>

    <section class="flex flex-col gap-4">
        <span class="font-mono text-sm text-ok-text">{{ __('milestones.detail.required', ['code' => $milestone->code, 'value' => $milestone->required_value]) }}</span>
        <h1 class="m-0 font-display text-[32px] leading-[1.1] font-semibold sm:text-[40px]">{{ $milestone->title() }}</h1>

        @if ($state === \App\Enums\MilestoneState::Verified)
            <div class="flex flex-wrap items-center gap-3.5 rounded-[14px] border border-ok/55 bg-ok/12 px-5 py-4">
                <x-icon.check :size="26" />
                <span class="font-display text-xl font-semibold">{{ __('milestones.detail.verified_banner', ['name' => $verified->user->display_name]) }}</span>
                <span class="text-sm text-ink-2 sm:ml-auto">{{ __('milestones.detail.verified_counts', ['indo' => config('genetic.indo.per_milestone')]) }}</span>
            </div>
        @elseif ($state === \App\Enums\MilestoneState::Pending)
            <div class="flex flex-wrap items-center gap-3.5 rounded-[14px] border border-dashed border-wait/75 px-5 py-4 text-wait-text">
                <x-icon.hourglass :size="24" />
                <span class="font-display text-xl font-semibold">{{ __('milestones.detail.pending_banner') }}</span>
                <span class="text-sm text-wait-dim sm:ml-auto">{{ __('milestones.detail.pending_text') }}</span>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-3.5 rounded-[14px] border border-line-head bg-sunken px-5 py-4 text-ink-2">
                <x-icon.lock :size="22" />
                <span class="font-display text-xl font-semibold">{{ __('milestones.detail.locked_banner') }}</span>
                <span class="text-sm text-ink-3 sm:ml-auto">{{ __('milestones.detail.locked_text') }}</span>
            </div>
        @endif
    </section>

    @if ($verified)
        <section aria-label="{{ __('milestones.detail.discovered_by') }}" class="grid grid-cols-[repeat(auto-fit,minmax(min(240px,100%),1fr))] gap-3">
            <x-fact :label="__('milestones.detail.discovered_by')" :value="$verified->user->display_name">
                @if ($verified->playerWasRenamed())
                    {{ __('milestones.detail.submitted_as', ['name' => $verified->player_name_snapshot]) }}
                @endif
            </x-fact>
            <x-fact :label="__('milestones.detail.submitted')">
                <x-slot:value><x-time :at="$verified->submitted_at" /></x-slot:value>
                <span class="font-mono">{{ __('milestones.detail.claim', ['id' => $verified->public_id]) }}</span>
            </x-fact>
            <x-fact :label="__('milestones.detail.verified_by')" :value="$verified->reviewer?->display_name ?? __('app.none')">
                {{ __('milestones.detail.admin') }}
            </x-fact>
            <x-fact :label="__('milestones.detail.verified_at')">
                <x-slot:value><x-time :at="$verified->reviewed_at" /></x-slot:value>
                @if ($verified->reviewed_at && $verified->submitted_at)
                    {{ __('milestones.detail.after_submission', ['duration' => $verified->reviewed_at->locale(app()->getLocale())->diffForHumans($verified->submitted_at, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2])]) }}
                @endif
            </x-fact>
        </section>
    @endif

    @auth
        @if ($state !== \App\Enums\MilestoneState::Verified && Route::has('submissions.create'))
            <div>
                <a href="{{ route('submissions.create', ['milestone' => $milestone->code]) }}" class="btn-primary">
                    <x-icon.plus />{{ __('milestones.detail.new_claim') }}
                </a>
            </div>
        @endif
    @endauth
</x-layout>
