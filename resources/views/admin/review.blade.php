@php
    $S = \App\Enums\SubmissionStatus::class;
    $fmt = \App\Support\Format::class;
@endphp
<x-layout :title="$current ? __('submissions.show.title', ['id' => $current->public_id]) : __('admin.queue.title')" area="admin" current="queue" width="max-w-[1320px]" class="flex flex-wrap items-start gap-6 pt-7!">
    {{-- ADM-1: queue --}}
    <section aria-labelledby="queue-title" class="card flex min-w-0 flex-[1_1_320px] flex-col gap-3 p-[18px]">
        <div class="flex flex-col gap-0.5 px-1">
            <h1 id="queue-title" class="m-0 font-display text-xl font-semibold">{{ __('admin.queue.title') }}</h1>
            <span class="text-[13px] text-ink-label">{{ __('admin.queue.subtitle') }}</span>
        </div>
        <form method="GET" action="{{ route('admin.queue') }}" role="search">
            <label for="queue-filter" class="sr-only">{{ __('admin.queue.filter_label') }}</label>
            <input id="queue-filter" type="search" name="q" value="{{ $filter }}" placeholder="{{ __('admin.queue.filter_placeholder') }}"
                   class="min-h-[42px] w-full rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink placeholder:text-ink-3 focus:border-ok focus:outline-none">
        </form>

        @if ($queue->isEmpty())
            <p class="m-0 px-1 py-3 text-sm text-ink-3">{{ $filter !== '' ? __('admin.queue.empty_filtered') : __('admin.queue.empty') }}</p>
        @else
            <ol class="m-0 flex list-none flex-col gap-2 p-0">
                @foreach ($queue as $item)
                    @php $selected = $current?->is($item); @endphp
                    <li>
                        <a href="{{ route('admin.submissions.show', ['submission' => $item, 'q' => $filter ?: null]) }}" @if ($selected) aria-current="true" @endif
                           @class([
                               'flex w-full flex-col gap-1 rounded-xl px-3.5 py-3 text-ink no-underline hover:text-ink',
                               'border-2 border-ok bg-ok/10' => $selected,
                               'border border-line bg-sunken hover:border-ink-3' => ! $selected,
                           ])>
                            <span class="flex w-full justify-between gap-2">
                                <span class="font-semibold">{{ $item->milestone->title() }}</span>
                                <span class="font-mono text-xs text-ink-label">{{ $item->public_id }}</span>
                            </span>
                            <span class="text-[13px] text-ink-2">{{ __('admin.queue.item_meta', [
                                'player' => $item->user->display_name,
                                'when' => $fmt::datetime($item->submitted_at),
                                'images' => trans_choice('app.count.images', $item->images_count),
                            ]) }}</span>
                            @if (($parallelCounts[$item->milestone_id] ?? 0) > 1)
                                <span class="text-xs text-wait-text">{{ trans_choice('admin.queue.parallel', $parallelCounts[$item->milestone_id]) }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- ADM-2: detail --}}
    <section aria-labelledby="detail-title" class="flex min-w-0 flex-[999_1_600px] flex-col gap-[18px]">
        @if ($current === null)
            <div class="card p-6 text-ink-3">{{ __('admin.detail.nothing_selected') }}</div>
        @else
            <div class="card flex flex-col gap-[18px] p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <span class="font-mono text-[13px] text-ok-text">{{ $current->milestone->code }} · {{ __('admin.detail.required', ['value' => $current->milestone->required_value]) }}</span>
                        <h2 id="detail-title" class="m-0 font-display text-[28px] font-semibold">{{ $current->milestone->title() }}</h2>
                        <span class="text-sm text-ink-label">{{ __('admin.detail.claim_line', ['id' => $current->public_id]) }} <x-time :at="$current->submitted_at" /></span>
                    </div>
                    <x-status-badge :status="$current->status" />
                </div>

                <div class="flex flex-wrap items-center gap-3.5 rounded-xl border border-line-head bg-sunken px-4 py-3.5">
                    <x-avatar :user="$current->user" :size="44" />
                    <div class="flex flex-[1_1_200px] flex-col gap-0.5">
                        <span class="font-semibold">{{ $current->user->display_name }}
                            @if ($current->playerWasRenamed())
                                <span class="font-normal text-ink-3">({{ __('milestones.detail.submitted_as', ['name' => $current->player_name_snapshot]) }})</span>
                            @endif
                        </span>
                        <span class="font-mono text-xs text-ink-label">{{ __('admin.detail.discord_id', ['id' => $current->user->discord_id]) }}</span>
                    </div>
                    <span class="text-[13px] text-ink-2">{{ __('admin.detail.player_history', ['verified' => $playerVerified, 'rejected' => $playerRejected]) }}</span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-xs font-semibold tracking-[0.1em] text-ink-label uppercase">{{ __('admin.detail.player_note') }}</span>
                    <p class="m-0 rounded-[10px] border border-line-head bg-sunken px-3.5 py-3 whitespace-pre-line {{ $current->player_note ? '' : 'text-ink-3' }}">{{ $current->player_note ?: __('admin.detail.no_note') }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <span class="text-xs font-semibold tracking-[0.1em] text-ink-label uppercase">{{ __('admin.detail.evidence') }}</span>
                        <span class="text-[13px] text-ink-label">{{ __('admin.detail.evidence_hint') }}</span>
                    </div>
                    <x-evidence-gallery :images="$current->images" :originals="true" height="h-[150px]" />
                </div>

                {{-- ADM-3, ADM-4 --}}
                <div class="flex flex-col gap-2">
                    <span class="text-xs font-semibold tracking-[0.1em] text-ink-label uppercase">{{ __('admin.detail.checks') }}</span>
                    @foreach ($parallel as $other)
                        @php $isEarlier = $other->submitted_at->lt($current->submitted_at) || ($other->submitted_at->eq($current->submitted_at) && $other->id < $current->id); @endphp
                        <x-check tone="wait" :tag="__('admin.checks.parallel_tag')">
                            {{ __('admin.checks.parallel', [
                                'id' => $other->public_id,
                                'name' => $other->user->display_name,
                                'order' => $isEarlier ? __('admin.checks.order_earlier') : __('admin.checks.order_later'),
                                'when' => $fmt::datetime($other->submitted_at),
                            ]) }}
                            <a href="{{ route('admin.submissions.show', $other) }}" class="link-underline">{{ $other->public_id }}</a>
                        </x-check>
                    @endforeach
                    @foreach ($earlier as $first)
                        <x-check tone="wait" :tag="__('admin.checks.later_tag')">
                            {{ __('admin.checks.later', ['id' => $first->public_id, 'name' => $first->user->display_name, 'when' => $fmt::datetime($first->submitted_at)]) }}
                        </x-check>
                    @endforeach
                    @forelse ($duplicates as $dup)
                        @foreach ($dup['others'] as $otherImage)
                            <x-check tone="bad" :tag="__('admin.checks.duplicate_tag')">
                                {{ __('admin.checks.duplicate', ['name' => $dup['image']->original_name, 'other' => $otherImage->submission->public_id, 'player' => $otherImage->submission->user->display_name]) }}
                                <a href="{{ route('admin.submissions.show', $otherImage->submission) }}" class="link-underline">{{ $otherImage->submission->public_id }}</a>
                            </x-check>
                        @endforeach
                    @empty
                        <x-check tone="ok" :tag="__('admin.checks.ok_tag')">{{ __('admin.checks.ok') }}</x-check>
                    @endforelse
                </div>

                @if ($current->notes->isNotEmpty())
                    <div class="flex flex-col gap-2">
                        @foreach ($current->notes as $note)
                            <div class="flex flex-col gap-1 rounded-[10px] border border-line-head bg-sunken px-3.5 py-3">
                                <span class="text-xs font-semibold tracking-[0.1em] uppercase {{ $note->isPublic() ? 'text-ink-label' : 'text-wait-text' }}">{{ $note->isPublic() ? __('admin.detail.public_notes') : __('admin.detail.internal_notes') }}</span>
                                <span class="whitespace-pre-line">{{ $note->body }} <span class="text-ink-3">· {{ $note->author->display_name }}, <x-time :at="$note->created_at" /></span></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($current->status !== $S::Pending)
                <div class="card p-6 text-ink-2" role="status">{{ __('admin.detail.not_pending', ['status' => $current->status->label()]) }}
                    @if ($current->rejection_reason) <span class="block pt-1 text-bad-text">{{ __('submissions.show.reason', ['reason' => $current->rejection_reason]) }}</span>@endif
                    @if ($current->revoke_reason) <span class="block pt-1 text-bad-text">{{ __('submissions.show.revoke_reason', ['reason' => $current->revoke_reason]) }}</span>@endif
                </div>
                <x-corrections-panel :submission="$current" :admins="$admins" />
            @elseif (! $canDecide)
                <div class="alert-warning" role="status">{{ __('admin.detail.own_claim') }}</div>
            @else
                {{-- ADM-5, ADM-6 --}}
                <div class="card grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-5 p-6">
                    <form method="POST" action="{{ route('admin.submissions.approve', ['submission' => $current, 'q' => $filter ?: null]) }}" class="flex flex-col gap-2.5" x-data="submitOnce" @submit="submit">
                        @csrf
                        <h3 class="m-0 text-base font-semibold">{{ __('admin.decision.approve') }}</h3>
                        <label for="public_note" class="text-[13px] text-ink-label">{{ __('admin.decision.public_note') }}</label>
                        <input id="public_note" name="public_note" type="text" maxlength="1000" value="{{ old('public_note') }}" placeholder="{{ __('admin.decision.public_note_placeholder') }}"
                               class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink placeholder:text-ink-3 focus:border-ok focus:outline-none">
                        <span class="text-[13px] text-ink-2">
                            {{ $parallel->isEmpty() ? __('admin.decision.effect_single') : trans_choice('admin.decision.effect_parallel', $parallel->count()) }}
                        </span>
                        @if ($earlier->isNotEmpty())
                            <label class="flex items-start gap-2.5 text-[13px] text-wait-text">
                                <input type="checkbox" name="confirm_later" value="1" required class="mt-0.5 size-4 accent-[#F2B24C]">
                                {{ __('admin.decision.confirm_later') }}
                            </label>
                        @endif
                        <button type="submit" class="btn-primary mt-auto min-h-12 rounded-[10px]" :disabled="submitting">
                            {{ $earlier->isNotEmpty() ? __('admin.decision.approve_anyway') : __('admin.decision.approve_button') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.submissions.reject', ['submission' => $current, 'q' => $filter ?: null]) }}" class="flex flex-col gap-2.5" x-data="submitOnce" @submit="submit">
                        @csrf
                        <h3 class="m-0 text-base font-semibold">{{ __('admin.decision.reject') }}</h3>
                        <label for="reason" class="text-[13px] text-ink-label">{{ __('admin.decision.reason') }}</label>
                        <textarea id="reason" name="reason" rows="3" required maxlength="1000" placeholder="{{ __('admin.decision.reason_placeholder') }}"
                                  class="resize-y rounded-[10px] border border-[#33404A] bg-page px-3 py-2.5 text-sm text-ink placeholder:text-ink-3 focus:border-ok focus:outline-none">{{ old('reason') }}</textarea>
                        @error('reason')<span class="text-[13px] text-bad-text">{{ $message }}</span>@enderror
                        <button type="submit" class="btn-danger mt-auto min-h-12 rounded-[10px]" :disabled="submitting">{{ __('admin.decision.reject_button') }}</button>
                    </form>
                </div>
            @endif

            @php $trailFormatter = new \App\Support\AuditFormatter($trailUsers); @endphp
            <section aria-labelledby="trail-title" class="card flex flex-col gap-3 p-6">
                <h3 id="trail-title" class="m-0 font-display text-xl font-semibold">{{ __('corrections.trail') }}</h3>
                @if ($trail->isEmpty())
                    <p class="m-0 text-sm text-ink-3">{{ __('corrections.trail_empty') }}</p>
                @else
                    <ol class="m-0 flex list-none flex-col p-0">
                        @foreach ($trail as $entry)
                            <li class="flex flex-col gap-1 border-t border-line-soft py-2.5 first:border-t-0">
                                <span class="font-semibold">{{ $entry->label() }} <span class="font-normal text-ink-3">· {{ $entry->actor?->display_name ?? __('audit.system') }} · <x-time :at="$entry->created_at" /></span></span>
                                <x-audit-change :entry="$entry" :formatter="$trailFormatter" />
                                @if ($entry->reason)<span class="text-[13px] text-ink-2">{{ __('audit.reason') }}: {{ $entry->reason }}</span>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif
    </section>
</x-layout>
