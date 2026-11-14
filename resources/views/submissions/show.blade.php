@php
    $S = \App\Enums\SubmissionStatus::class;
    $s = $submission;
    $isAuthor = auth()->id() === $s->user_id;
@endphp
<x-layout :title="__('submissions.show.title', ['id' => $s->public_id])" :current="$isAuthor ? 'dashboard' : null" width="max-w-[1040px]" class="flex flex-col gap-7">
    @if (session('submitted'))
        <section aria-live="polite" class="flex flex-col gap-3.5 rounded-2xl border border-ok/50 bg-surface p-8">
            <x-status-badge :status="$s->status" class="self-start" />
            <h1 class="m-0 font-display text-[28px] font-semibold">{{ __('submissions.show.submitted_title', ['id' => $s->public_id]) }}</h1>
            <p class="m-0 max-w-[640px] text-ink-2">{{ __('submissions.show.submitted_text', ['milestone' => $s->milestone->dinosaurName().' → '.$s->milestone->statName()]) }}</p>
            <div class="mt-1.5 flex flex-wrap gap-3">
                <a href="{{ route('dashboard') }}" class="btn-primary min-h-[46px] rounded-[10px] px-[18px]">{{ __('submissions.show.back_to_dashboard') }}</a>
                <a href="{{ route('submissions.create') }}" class="btn-ghost min-h-[46px] rounded-[10px] px-[18px]">{{ __('submissions.show.send_another') }}</a>
            </div>
        </section>
    @else
        <nav aria-label="{{ __('app.breadcrumbs') }}" class="flex flex-wrap gap-2 text-sm text-ink-3">
            @if ($isAuthor)
                <a href="{{ route('dashboard') }}" class="link-underline">{{ __('app.nav.dashboard') }}</a>
            @else
                <a href="{{ route('milestones.show', $s->milestone) }}" class="link-underline">{{ $s->milestone->title() }}</a>
            @endif
            <span aria-hidden="true">/</span><span>{{ $s->public_id }}</span>
        </nav>
    @endif

    <section class="flex flex-col gap-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-2">
                <span class="font-mono text-sm text-ok-text">{{ $s->milestone->code }} · {{ __('admin.detail.required', ['value' => $s->milestone->required_value]) }}</span>
                @if (session('submitted'))
                    <h2 class="m-0 font-display text-[26px] font-semibold">{{ $s->milestone->title() }}</h2>
                @else
                    <h1 class="m-0 font-display text-[30px] font-semibold">{{ $s->milestone->title() }}</h1>
                @endif
                <span class="text-sm text-ink-2">{{ __('admin.detail.claim_line', ['id' => $s->public_id]) }} <x-time :at="$s->submitted_at" /></span>
            </div>
            <x-status-badge :status="$s->status" />
        </div>

        @if ($s->status === $S::Rejected && $s->rejection_reason)
            <div class="alert-error">{{ __('submissions.show.reason', ['reason' => $s->rejection_reason]) }}</div>
        @elseif ($s->status === $S::Revoked && $s->revoke_reason)
            <div class="alert-error">{{ __('submissions.show.revoke_reason', ['reason' => $s->revoke_reason]) }}</div>
        @elseif ($s->status === $S::Pending && $otherPending > 0 && ($isAuthor || auth()->user()?->isAdmin()))
            <div class="alert-warning">{{ trans_choice('submissions.show.other_pending', $otherPending) }}</div>
        @endif
    </section>

    <section class="grid grid-cols-[repeat(auto-fit,minmax(min(220px,100%),1fr))] gap-3">
        <x-fact :label="__('submissions.show.player')" :value="$s->user->display_name">
            @if ($s->playerWasRenamed())
                {{ __('milestones.detail.submitted_as', ['name' => $s->player_name_snapshot]) }}
            @endif
        </x-fact>
        <x-fact :label="__('submissions.show.submitted_at')">
            <x-slot:value><x-time :at="$s->submitted_at" /></x-slot:value>
            <span class="font-mono">{{ $s->public_id }}</span>
        </x-fact>
        @if ($s->reviewed_at)
            <x-fact :label="__('submissions.show.reviewed')">
                <x-slot:value><x-time :at="$s->reviewed_at" /></x-slot:value>
                {{ __('submissions.show.reviewer', ['name' => $s->reviewer?->display_name ?? __('app.none')]) }}
            </x-fact>
        @endif
    </section>

    <section aria-labelledby="note-title" class="flex flex-col gap-2">
        <h2 id="note-title" class="label-caps m-0 text-xs">{{ __('submissions.show.note') }}</h2>
        <p class="m-0 rounded-[10px] border border-line bg-surface px-4 py-3 whitespace-pre-line {{ $s->player_note ? '' : 'text-ink-3' }}">{{ $s->player_note ?: __('submissions.show.no_note') }}</p>
    </section>

    <section aria-labelledby="evidence-title" class="card flex flex-col gap-3.5 px-6 py-[22px]">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="evidence-title" class="m-0 font-display text-xl font-semibold">{{ __('submissions.show.evidence') }}</h2>
            <span class="text-sm text-ink-3">{{ __('submissions.show.zoom_hint') }}</span>
        </div>
        <x-evidence-gallery :images="$s->images" :originals="auth()->user()?->isAdmin() ?? false" />
    </section>

    <div class="flex flex-wrap gap-3">
        @can('withdraw', $s)
            <form method="POST" action="{{ route('submissions.withdraw', $s) }}">
                @csrf
                <button type="submit" class="btn-ghost">{{ __('submissions.withdraw.button') }}</button>
            </form>
        @endcan
        <a href="{{ route('milestones.show', $s->milestone) }}" class="btn-ghost">{{ __('submissions.show.view_milestone') }}</a>
    </div>
</x-layout>
