<x-layout :title="$milestone->title()" current="milestones" class="flex flex-col gap-7">
    <nav aria-label="{{ __('app.breadcrumbs') }}" class="flex flex-wrap gap-2 text-sm text-ink-3">
        <a href="{{ route('home') }}" class="link-underline">{{ __('app.nav.milestones') }}</a>
        <span aria-hidden="true">/</span><span>{{ $milestone->dinosaurName() }}</span>
        <span aria-hidden="true">/</span><span>{{ $milestone->statName() }}</span>
    </nav>

    <section class="flex flex-col gap-4">
        <span class="font-mono text-sm text-ok-text">{{ __('milestones.detail.required', ['code' => $milestone->code, 'value' => $milestone->required_value]) }}</span>
        <h1 class="m-0 font-display text-[32px] leading-[1.1] font-semibold sm:text-[40px]">{{ $milestone->title() }}</h1>

        @unless ($milestone->isActive())
            <p class="alert-warning m-0" role="note">{{ __('milestones.detail.disabled_notice') }}</p>
        @endunless

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

    @if ($verified && ($verified->images->isNotEmpty() || $verified->notes->isNotEmpty()))
        <section aria-labelledby="evidence-title" class="card flex flex-col gap-3.5 px-6 py-[22px]">
            <h2 id="evidence-title" class="m-0 font-display text-xl font-semibold">{{ __('milestones.detail.evidence') }}</h2>
            @if ($verified->images->isNotEmpty())
                <x-evidence-gallery :images="$verified->images" :originals="$isAdmin" />
            @endif
            @foreach ($verified->notes as $note)
                <div class="flex flex-col gap-1 rounded-[10px] border border-line-head bg-sunken px-3.5 py-3">
                    <span class="text-xs font-semibold tracking-[0.1em] uppercase {{ $note->isPublic() ? 'text-ink-label' : 'text-wait-text' }}">{{ $note->isPublic() ? __('milestones.detail.admin_note') : __('admin.detail.internal_notes') }}</span>
                    <span class="whitespace-pre-line">{{ $note->body }} <span class="text-ink-3">· {{ $note->author->display_name }}</span></span>
                </div>
            @endforeach
        </section>
    @endif

    @if ($verified || $isAdmin)
        <section aria-labelledby="history-title" class="grid grid-cols-[repeat(auto-fit,minmax(min(460px,100%),1fr))] items-start gap-5">
            @if ($verified)
                {{-- HIS-1 --}}
                <div class="card flex flex-col gap-2.5 px-6 py-[22px]">
                    <h2 id="history-title" class="m-0 mb-1 font-display text-xl font-semibold">{{ __('milestones.detail.history') }}</h2>
                    <ol class="m-0 flex list-none flex-col p-0">
                        <li class="grid grid-cols-[14px_minmax(0,1fr)] gap-3.5 py-2.5">
                            <span aria-hidden="true" class="mt-[5px] size-3 rounded-full bg-ok"></span>
                            <span class="flex flex-col gap-0.5">
                                <span class="font-semibold">{{ __('milestones.history.verified', ['admin' => $verified->reviewer?->display_name ?? __('app.none')]) }}</span>
                                <span class="text-[13px] text-ink-3"><x-time :at="$verified->reviewed_at" />@if ($announced) · {{ __('milestones.history.announced') }}@endif</span>
                            </span>
                        </li>
                        <li class="grid grid-cols-[14px_minmax(0,1fr)] gap-3.5 border-t border-line-soft py-2.5">
                            <span aria-hidden="true" class="mt-[5px] size-3 rounded-full border-2 border-wait"></span>
                            <span class="flex flex-col gap-0.5">
                                <span class="font-semibold">{{ __('milestones.history.submitted', ['id' => $verified->public_id, 'name' => $verified->user->display_name]) }}</span>
                                <span class="text-[13px] text-ink-3"><x-time :at="$verified->submitted_at" /> · {{ trans_choice('app.count.screenshots', $verified->images->count()) }}</span>
                            </span>
                        </li>
                    </ol>
                </div>
            @endif

            @if ($isAdmin)
                {{-- HIS-2 --}}
                <div class="flex flex-col gap-2.5 rounded-2xl border border-dashed border-[#33404A] bg-sunken px-6 py-[22px]">
                    <div class="mb-1 flex items-baseline justify-between gap-3">
                        <h2 @if (! $verified) id="history-title" @endif class="m-0 font-display text-xl font-semibold">{{ __('milestones.earlier.title') }}</h2>
                        <span class="text-xs font-semibold tracking-[0.06em] text-wait-text">{{ __('milestones.earlier.admins_only') }}</span>
                    </div>
                    @if ($earlier->isEmpty())
                        <p class="m-0 text-sm text-ink-3">{{ __('milestones.earlier.empty') }}</p>
                    @else
                        <ol class="m-0 flex list-none flex-col p-0">
                            @foreach ($earlier as $attempt)
                                <li class="flex flex-col gap-1 border-t border-line-soft py-2.5 first:border-t-0">
                                    <span class="flex justify-between gap-3">
                                        <a href="{{ route('admin.submissions.show', $attempt) }}" class="font-semibold text-ink no-underline hover:text-ink">{{ __('milestones.earlier.attempt', ['id' => $attempt->public_id, 'name' => $attempt->user->display_name]) }}</a>
                                        <x-status-badge :status="$attempt->status" />
                                    </span>
                                    <span class="text-[13px] text-ink-3">
                                        <x-time :at="$attempt->submitted_at" />
                                        @if ($attempt->revoker) · {{ __('milestones.earlier.revoked_by', ['admin' => $attempt->revoker->display_name]) }}
                                        @elseif ($attempt->reviewer) · {{ __('milestones.earlier.reviewed_by', ['admin' => $attempt->reviewer->display_name]) }}
                                        @endif
                                    </span>
                                    @if ($attempt->rejection_reason)
                                        <span class="text-sm text-bad-text">{{ __('submissions.show.reason', ['reason' => $attempt->rejection_reason]) }}</span>
                                    @elseif ($attempt->revoke_reason)
                                        <span class="text-sm text-bad-text">{{ __('submissions.show.revoke_reason', ['reason' => $attempt->revoke_reason]) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if ($verified)
                        <div class="mt-1.5 flex flex-wrap gap-2.5 border-t border-line-soft pt-3.5">
                            <a href="{{ route('admin.submissions.show', $verified) }}#corrections" class="btn-danger min-h-11 px-3.5 text-sm font-medium">{{ __('corrections.revoke') }}</a>
                            <a href="{{ route('admin.submissions.show', $verified) }}#corrections" class="btn-ghost min-h-11 px-3.5 text-sm">{{ __('corrections.change_verifier') }}</a>
                            <a href="{{ route('admin.submissions.show', $verified) }}#corrections" class="btn-ghost min-h-11 px-3.5 text-sm">{{ __('corrections.add_note') }}</a>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @endif

    @auth
        @if ($state !== \App\Enums\MilestoneState::Verified)
            <div>
                <a href="{{ route('submissions.create', ['milestone' => $milestone->code]) }}" class="btn-primary">
                    <x-icon.plus />{{ __('milestones.detail.new_claim') }}
                </a>
            </div>
        @endif
    @endauth
</x-layout>
