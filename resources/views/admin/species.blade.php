@php
    $verified = \App\Enums\SubmissionStatus::Verified->value;
    $pending = \App\Enums\SubmissionStatus::Pending->value;
    $addErrors = $errors->getBag('add');
    $accept = 'image/png,image/jpeg,image/webp';
@endphp
<x-layout :title="__('admin.species.title')" area="admin" current="species" class="flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="m-0 font-display text-[28px] font-semibold">{{ __('admin.species.title') }}</h1>
        <p class="m-0 max-w-3xl text-ink-2">{{ __('admin.species.subtitle') }}</p>
        <p class="m-0 text-sm text-ink-3">
            {{ __('admin.species.summary', [
                'species' => trans_choice('app.count.species', $activeSpecies),
                'milestones' => trans_choice('app.count.milestones', $summary['total']),
                'indo' => $summary['indo_max'],
            ]) }}
            · {{ __('admin.species.discord_note') }}
        </p>
        <p id="species-image-hint" class="m-0 max-w-3xl text-sm text-ink-3">{{ __('admin.species.image_hint', ['max' => config('genetic.upload.max_mb'), 'size' => \App\Services\SpeciesArt::MAX_SIZE]) }}</p>
    </div>

    <ol class="m-0 flex list-none flex-col gap-3 p-0">
        @foreach ($species as $item)
            @php
                $bag = $errors->getBag("species_{$item->code}");
                $failed = $bag->any();
                $checkedStats = $failed ? (array) old('stats', []) : $item->milestones->where('is_active', true)->pluck('stat')->all();
                $isActive = $failed ? old('is_active') === '1' : $item->is_active;
                $counts = $claims->get($item->code, collect());
                $art = $item->imageUrl();
            @endphp
            <li @class(['card flex gap-4 p-4', 'border-dashed opacity-75' => ! $item->is_active])>
                {{-- Order --}}
                <div class="flex flex-col gap-1.5">
                    <form method="POST" action="{{ route('admin.species.move', $item) }}">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="btn-ghost min-h-10 w-10 px-0 font-mono" @disabled($loop->first) aria-label="{{ __('admin.species.move_up', ['name' => $item->name]) }}">↑</button>
                    </form>
                    <form method="POST" action="{{ route('admin.species.move', $item) }}">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="btn-ghost min-h-10 w-10 px-0 font-mono" @disabled($loop->last) aria-label="{{ __('admin.species.move_down', ['name' => $item->name]) }}">↓</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.species.update', $item) }}" enctype="multipart/form-data" class="flex min-w-0 flex-1 flex-col gap-3" aria-label="{{ $item->name }}">
                    @csrf
                    @method('PUT')

                    <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                        <label class="flex w-full max-w-sm min-w-[220px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
                            {{ __('admin.species.name') }}
                            <input type="text" name="name" value="{{ $failed ? old('name', $item->name) : $item->name }}" required maxlength="64" class="field">
                        </label>
                        <span class="flex w-24 flex-col gap-1 text-[13px] text-ink-label">
                            {{ __('admin.species.code') }}
                            <span class="flex min-h-11 items-center font-mono text-sm text-ink">{{ $item->code }}</span>
                        </span>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($isActive) class="size-4 accent-[#45D19E]">
                            {{ __('admin.species.active') }}
                        </label>
                        @unless ($item->is_active)
                            <span class="badge-muted mb-3">{{ __('admin.species.inactive_badge') }}</span>
                        @endunless
                        <span class="mb-3 text-[13px] text-ink-3 sm:ml-auto">{{ __('admin.species.claims', ['verified' => $counts->get($verified, 0), 'pending' => $counts->get($pending, 0)]) }}</span>
                    </div>

                    <fieldset class="m-0 flex flex-wrap items-center gap-x-5 gap-y-1 border-0 p-0">
                        <legend class="mb-1 p-0 text-[13px] text-ink-label">{{ __('admin.species.stats') }}</legend>
                        @foreach ($stats as $statCode => $statName)
                            <label class="flex min-h-10 items-center gap-2 text-sm">
                                <input type="checkbox" name="stats[]" value="{{ $statCode }}" @checked(in_array($statCode, $checkedStats, true)) class="size-4 accent-[#45D19E]">
                                {{ $statName }}
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="flex flex-wrap items-end gap-x-5 gap-y-3">
                        <span class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line-soft bg-sunken">
                            @if ($art)
                                <img src="{{ $art }}" alt="{{ __('admin.species.image_alt', ['name' => $item->name]) }}" decoding="async" class="h-full w-full object-contain">
                            @else
                                <span class="px-2 text-center text-xs text-ink-3">{{ __('admin.species.no_image') }}</span>
                            @endif
                        </span>
                        <label class="flex max-w-sm min-w-[220px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
                            {{ $item->image !== null ? __('admin.species.image_replace') : __('admin.species.image') }}
                            <input type="file" name="image" accept="{{ $accept }}" class="field-file" aria-describedby="species-image-hint">
                        </label>
                        <label class="flex min-h-10 items-center gap-2 text-sm">
                            <input type="checkbox" name="flip" value="1" @checked($failed && old('flip')) class="size-4 accent-[#45D19E]">
                            {{ __('admin.species.flip') }}
                        </label>
                        @if ($item->image !== null)
                            <label class="flex min-h-10 items-center gap-2 text-sm">
                                <input type="checkbox" name="remove_image" value="1" @checked($failed && old('remove_image')) class="size-4 accent-[#45D19E]">
                                {{ __('admin.species.remove_image') }}
                            </label>
                        @endif
                        <button type="submit" class="btn-primary ml-auto">{{ __('admin.species.save') }}</button>
                    </div>

                    @if ($failed)
                        <p class="m-0 text-[13px] text-bad-text" role="alert">{{ $bag->first() }}</p>
                    @endif
                </form>
            </li>
        @endforeach
    </ol>

    <section class="card flex flex-col gap-3 p-5" aria-labelledby="add-species-title">
        <h2 id="add-species-title" class="m-0 font-display text-xl font-semibold">{{ __('admin.species.add_title') }}</h2>
        <p class="m-0 max-w-3xl text-sm text-ink-3">{{ __('admin.species.add_hint') }}</p>
        <form method="POST" action="{{ route('admin.species.store') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
            @csrf
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex w-44 flex-col gap-1 text-[13px] text-ink-label">
                    {{ __('admin.species.code') }}
                    <input type="text" name="code" value="{{ $addErrors->any() ? old('code') : '' }}" required maxlength="16" autocomplete="off" class="field font-mono uppercase" placeholder="REX">
                </label>
                <label class="flex min-w-[220px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
                    {{ __('admin.species.name') }}
                    <input type="text" name="name" value="{{ $addErrors->any() ? old('name') : '' }}" required maxlength="64" class="field" placeholder="Rex">
                </label>
            </div>
            <div class="flex flex-wrap items-end gap-x-5 gap-y-3">
                <label class="flex max-w-sm min-w-[220px] flex-1 flex-col gap-1 text-[13px] text-ink-label">
                    {{ __('admin.species.image_optional') }}
                    <input type="file" name="image" accept="{{ $accept }}" class="field-file" aria-describedby="species-image-hint">
                </label>
                <label class="flex min-h-10 items-center gap-2 text-sm">
                    <input type="checkbox" name="flip" value="1" @checked($addErrors->any() && old('flip')) class="size-4 accent-[#45D19E]">
                    {{ __('admin.species.flip') }}
                </label>
                <button type="submit" class="btn-primary ml-auto">{{ __('admin.species.add') }}</button>
            </div>
        </form>
        @if ($addErrors->any())
            <ul class="m-0 flex list-none flex-col gap-1 p-0 text-[13px] text-bad-text" role="alert">
                @foreach ($addErrors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout>
