@php $fmt = \App\Support\Format::class; @endphp
<x-layout :title="__('app.nav.all_submissions')" area="admin" current="submissions" width="max-w-[1320px]" class="flex flex-col gap-6">
    <h1 class="m-0 font-display text-[28px] font-semibold">{{ __('app.nav.all_submissions') }}</h1>

    <form method="GET" action="{{ route('admin.submissions') }}" class="card flex flex-wrap items-end gap-3 p-4" role="search">
        <label class="flex min-w-[170px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('admin.list.status') }}
            <select name="status" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                <option value="">{{ __('app.all') }}</option>
                @foreach (\App\Enums\SubmissionStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[200px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('admin.list.milestone') }}
            <select name="milestone" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                <option value="">{{ __('app.all') }}</option>
                @foreach ($milestones as $milestone)
                    <option value="{{ $milestone->code }}" @selected(($filters['milestone'] ?? '') === $milestone->code)>{{ $milestone->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[170px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('admin.list.player') }}
            <input type="search" name="player" value="{{ $filters['player'] ?? '' }}" placeholder="{{ __('admin.list.player_placeholder') }}" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink placeholder:text-ink-3">
        </label>
        <label class="flex min-w-[170px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('admin.list.admin') }}
            <select name="admin" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                <option value="">{{ __('app.all') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((int) ($filters['admin'] ?? 0) === $admin->id)>{{ $admin->display_name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">{{ __('app.filter') }}</button>
            <a href="{{ route('admin.submissions') }}" class="btn-ghost">{{ __('app.reset_filter') }}</a>
        </div>
    </form>

    <div class="card overflow-x-auto px-2 sm:px-5">
        @if ($submissions->isEmpty())
            <p class="m-0 p-4 text-ink-3">{{ __('admin.list.empty') }}</p>
        @else
            <table class="w-full min-w-[860px] border-separate border-spacing-0 text-left text-sm">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.08em] text-ink-label uppercase">
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('admin.list.id') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('admin.list.milestone') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('admin.list.player') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('admin.list.status') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('admin.list.submitted') }}</th>
                        <th scope="col" class="py-3 font-semibold">{{ __('admin.list.admin') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submissions as $s)
                        <tr>
                            <td class="border-t border-line-soft py-3 pr-3"><a href="{{ route('admin.submissions.show', $s) }}" class="font-mono">{{ $s->public_id }}</a></td>
                            <td class="border-t border-line-soft py-3 pr-3 font-medium">{{ $s->milestone->title() }}</td>
                            <td class="border-t border-line-soft py-3 pr-3">{{ $s->user->display_name }}</td>
                            <td class="border-t border-line-soft py-3 pr-3"><x-status-badge :status="$s->status" /></td>
                            <td class="border-t border-line-soft py-3 pr-3 text-ink-2"><x-time :at="$s->submitted_at" /></td>
                            <td class="border-t border-line-soft py-3 text-ink-2">{{ $s->revoker?->display_name ?? $s->reviewer?->display_name ?? __('app.none') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $submissions->links() }}
</x-layout>
