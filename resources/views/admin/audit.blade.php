@php $formatter = new \App\Support\AuditFormatter($users); @endphp
<x-layout :title="__('audit.title')" area="admin" current="audit" width="max-w-[1320px]" class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <h1 class="m-0 font-display text-[28px] font-semibold">{{ __('audit.title') }}</h1>
        <p class="m-0 text-ink-2">{{ __('audit.subtitle') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.audit') }}" class="card flex flex-wrap items-end gap-3 p-4" role="search">
        <label class="flex min-w-[200px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('audit.filter_action') }}
            <select name="action" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                <option value="">{{ __('app.all') }}</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ \App\Models\AuditLog::labelFor($action) }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[170px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('audit.filter_actor') }}
            <select name="actor" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                <option value="">{{ __('app.all') }}</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}" @selected((int) ($filters['actor'] ?? 0) === $actor->id)>{{ $actor->display_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex min-w-[150px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
            {{ __('audit.filter_submission') }}
            <input type="search" name="submission" value="{{ $filters['submission'] ?? '' }}" placeholder="S-000123" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 font-mono text-sm text-ink placeholder:text-ink-3">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">{{ __('app.filter') }}</button>
            <a href="{{ route('admin.audit') }}" class="btn-ghost">{{ __('app.reset_filter') }}</a>
        </div>
    </form>

    <div class="card overflow-x-auto px-2 sm:px-5">
        @if ($entries->isEmpty())
            <p class="m-0 p-4 text-ink-3">{{ __('audit.empty') }}</p>
        @else
            <table class="w-full min-w-[900px] border-separate border-spacing-0 text-left text-sm">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.08em] text-ink-label uppercase">
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('audit.when') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('audit.actor') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('audit.action_col') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('audit.entity') }}</th>
                        <th scope="col" class="py-3 pr-3 font-semibold">{{ __('audit.change') }}</th>
                        <th scope="col" class="py-3 font-semibold">{{ __('audit.reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php $submission = $entry->entity === 'submissions' ? $submissions->get($entry->entity_id) : null; @endphp
                        <tr class="align-top">
                            <td class="border-t border-line-soft py-3 pr-3 whitespace-nowrap text-ink-2"><x-time :at="$entry->created_at" /></td>
                            <td class="border-t border-line-soft py-3 pr-3">{{ $entry->actor?->display_name ?? __('audit.system') }}</td>
                            <td class="border-t border-line-soft py-3 pr-3 font-medium">{{ $entry->label() }}</td>
                            <td class="border-t border-line-soft py-3 pr-3">
                                @if ($submission)
                                    <a href="{{ route('admin.submissions.show', $submission) }}" class="font-mono">{{ $submission->public_id }}</a>
                                    <span class="block text-[13px] text-ink-3">{{ $submission->milestone->title() }}</span>
                                @else
                                    <span class="font-mono text-ink-3">{{ $entry->entity }} #{{ $entry->entity_id }}</span>
                                @endif
                            </td>
                            <td class="border-t border-line-soft py-3 pr-3"><x-audit-change :entry="$entry" :formatter="$formatter" /></td>
                            <td class="border-t border-line-soft py-3 whitespace-pre-line text-ink-2">{{ $entry->reason ?? __('app.none') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $entries->links() }}
</x-layout>
