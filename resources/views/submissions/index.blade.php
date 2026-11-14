<x-layout :title="__('submissions.index.title')" current="dashboard" class="flex flex-col gap-6">
    <nav aria-label="{{ __('app.breadcrumbs') }}" class="flex flex-wrap gap-2 text-sm text-ink-3">
        <a href="{{ route('dashboard') }}" class="link-underline">{{ __('app.nav.dashboard') }}</a>
        <span aria-hidden="true">/</span><span>{{ __('submissions.index.title') }}</span>
    </nav>

    <h1 class="m-0 font-display text-[30px] font-semibold">{{ __('submissions.index.title') }}</h1>

    <section class="card px-6 py-3">
        @if ($submissions->isEmpty())
            <p class="m-0 py-3 text-ink-3">{{ __('submissions.index.empty') }}</p>
        @else
            <ul class="m-0 flex list-none flex-col p-0 [&>li:first-child]:border-t-0">
                @foreach ($submissions as $submission)
                    <x-submission-row :submission="$submission" :other-pending="$otherPending[$submission->milestone_id] ?? 0" :claimable="! isset($blocked[$submission->milestone_id])" />
                @endforeach
            </ul>
        @endif
    </section>

    {{ $submissions->links() }}
</x-layout>
