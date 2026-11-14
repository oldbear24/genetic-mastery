@php
    $P = \App\Services\PlayerMilestones::class;
    $requiredValue = config('genetic.required_value');
    $selectedSpecies = $selected?->dinosaur ?? $grid->keys()->first();

    $claimData = [
        'species' => $selectedSpecies,
        'selected' => $selected?->id,
        'milestones' => $grid->flatMap(fn ($row) => $row['milestones'])->mapWithKeys(fn ($m) => [$m->id => [
            'code' => $m->code,
            'species' => $m->dinosaur,
            'open' => $board->claimable($m),
            'label' => __('submissions.form.claiming_value', ['species' => $m->dinosaurName(), 'stat' => $m->statName(), 'value' => $m->required_value]),
        ]]),
        'bySpecies' => $grid->map(fn ($row) => $row['milestones']->pluck('id')->values()),
    ];
    $uploadConfig = [
        'maxFiles' => (int) config('genetic.upload.max_files'),
        'maxMb' => (int) config('genetic.upload.max_mb'),
        'messages' => [
            'tooMany' => __('submissions.form.client_too_many', ['max' => config('genetic.upload.max_files')]),
            'tooLarge' => __('submissions.form.client_too_large', ['name' => ':name', 'mb' => config('genetic.upload.max_mb')]),
            'wrongType' => __('submissions.form.client_wrong_type', ['name' => ':name']),
        ],
    ];
    $stepNumber = 'inline-flex size-[26px] items-center justify-center rounded-full border border-[#33404A] bg-raised font-mono text-[13px]';
@endphp
<x-layout :title="__('submissions.form.title')" current="dashboard" width="max-w-[1040px]">
    <script type="application/json" id="claim-data">@json($claimData)</script>
    <script type="application/json" id="upload-config">@json($uploadConfig)</script>

    <nav aria-label="{{ __('app.breadcrumbs') }}" class="mb-7 flex flex-wrap gap-2 text-sm text-ink-3">
        <a href="{{ route('dashboard') }}" class="link-underline">{{ __('app.nav.dashboard') }}</a>
        <span aria-hidden="true">/</span><span>{{ __('submissions.form.title') }}</span>
    </nav>

    <form method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data" class="flex flex-col gap-7" x-data="submitOnce" @submit="submit">
        @csrf

        <div class="flex flex-col gap-1.5">
            <h1 class="m-0 font-display text-[30px] font-semibold">{{ __('submissions.form.title') }}</h1>
            <p class="m-0 text-ink-2">{{ __('submissions.form.intro') }}</p>
        </div>

        @if ($errors->any())
            <div class="alert-error" role="alert">
                <ul class="m-0 list-none p-0">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-data="claimPicker" class="flex flex-col gap-7">
            {{-- 1: species --}}
            <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                <legend class="mb-3 flex items-center gap-2.5 p-0 text-base font-semibold"><span class="{{ $stepNumber }}">1</span>{{ __('submissions.form.step_species') }}</legend>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fit,minmax(180px,1fr))]">
                    @foreach ($grid as $code => $row)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="species" value="{{ $code }}" x-model="species" class="peer sr-only" @checked($code === $selectedSpecies)>
                            <span class="flex min-h-16 flex-col items-start justify-center gap-0.5 rounded-xl border border-line bg-surface px-3.5 py-2.5 peer-checked:border-2 peer-checked:border-ok peer-checked:bg-ok/12 peer-focus-visible:outline-2 peer-focus-visible:outline-ok-text">
                                <span class="font-semibold">{{ $row['name'] }}</span>
                                <span class="text-[13px] text-ink-label">{{ __('submissions.form.species_verified', ['count' => $row['verified'], 'total' => $row['milestones']->count()]) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- 2: stat --}}
            <fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
                <legend class="mb-3 flex items-center gap-2.5 p-0 text-base font-semibold"><span class="{{ $stepNumber }}">2</span>{{ __('submissions.form.step_stat') }}</legend>
                @foreach ($grid as $code => $row)
                    <div class="flex flex-col gap-2" x-show="isSpecies('{{ $code }}')" @if ($code !== $selectedSpecies) x-cloak @endif>
                        <span class="sr-only">{{ $row['name'] }}</span>
                        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-2.5">
                            @foreach ($row['milestones'] as $m)
                                @php
                                    $state = $board->state($m);
                                    $open = $board->claimable($m);
                                    [$note, $noteClass] = match ($state) {
                                        $P::LOCKED => [__('submissions.form.stat_open'), 'text-ink-label'],
                                        $P::PENDING => [__('submissions.form.stat_pending_other'), 'text-wait-text'],
                                        $P::MINE_REJECTED => [__('submissions.form.stat_rejected'), 'text-bad-text'],
                                        $P::MINE_PENDING => [__('submissions.form.stat_mine_pending'), 'text-ink-3'],
                                        $P::MINE_VERIFIED => [__('submissions.form.stat_mine_verified'), 'text-ink-3'],
                                        default => [__('submissions.form.stat_verified', ['name' => $board->submission($m)?->user->display_name]), 'text-ink-3'],
                                    };
                                @endphp
                                <label @class(['relative', 'cursor-pointer' => $open, 'cursor-not-allowed' => ! $open])>
                                    <input type="radio" name="milestone_id" value="{{ $m->id }}" x-model="milestoneId" class="peer sr-only" @checked($selected?->id === $m->id) @disabled(! $open)>
                                    <span @class([
                                        'flex min-h-[60px] items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 peer-focus-visible:outline-2 peer-focus-visible:outline-ok-text',
                                        'border border-[#33404A] bg-surface text-ink peer-checked:border-2 peer-checked:border-ok peer-checked:bg-ok/12' => $open,
                                        'border border-line-soft bg-[#101416] text-ink-3' => ! $open,
                                    ])>
                                        <span class="font-semibold">{{ $m->statName() }}</span>
                                        <span class="text-right text-[13px] {{ $noteClass }}">{{ $note }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2.5 rounded-xl border border-line bg-surface px-4 py-3.5" aria-live="polite">
                    <template x-if="hasSelection">
                        <span class="contents">
                            <span class="text-[13px] text-ink-label">{{ __('submissions.form.claiming') }}</span>
                            <span class="font-mono text-sm font-semibold text-ok-text" x-text="selectedCode"></span>
                            <span class="text-sm" x-text="selectedLabel"></span>
                        </span>
                    </template>
                    <template x-if="!hasSelection">
                        <span class="text-sm text-ink-3">{{ __('submissions.form.nothing_open') }}</span>
                    </template>
                    <noscript>
                        @if ($selected)
                            <span class="text-[13px] text-ink-label">{{ __('submissions.form.claiming') }}</span>
                            <span class="font-mono text-sm font-semibold text-ok-text">{{ $selected->code }}</span>
                        @endif
                    </noscript>
                </div>
            </fieldset>
        </div>

        {{-- 3: screenshots --}}
        <section aria-labelledby="step-shots" class="flex flex-col gap-3" x-data="evidenceUpload">
            <h2 id="step-shots" class="m-0 flex items-center gap-2.5 text-base font-semibold"><span class="{{ $stepNumber }}">3</span>{{ __('submissions.form.step_screenshots') }}</h2>

            <label for="images" class="flex min-h-[140px] cursor-pointer flex-col items-center justify-center gap-2 rounded-[14px] border-[1.5px] border-dashed border-[#3A4952] bg-sunken p-5 text-center"
                   :class="dragging ? 'border-ok bg-ok/5' : ''"
                   @dragover.prevent="dragover" @dragleave.prevent="dragleave" @drop.prevent="dropped">
                <x-icon.upload :size="28" />
                <span class="font-semibold">{{ __('submissions.form.drop') }} <span class="text-ok-text underline">{{ __('submissions.form.browse') }}</span></span>
                <span class="text-[13px] text-ink-label">{{ __('submissions.form.drop_hint', ['files' => config('genetic.upload.max_files'), 'mb' => config('genetic.upload.max_mb')]) }}</span>
                <input id="images" type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp" x-ref="input" @change="picked" class="sr-only">
            </label>

            <ul class="m-0 flex list-none flex-col gap-2 p-0" role="alert" x-show="hasErrors" x-cloak>
                <template x-for="error in errors" :key="error">
                    <li class="alert-error" x-text="error"></li>
                </template>
            </ul>

            <ul aria-label="{{ __('submissions.form.selected') }}" class="m-0 flex list-none flex-col gap-2.5 p-0" x-show="hasFiles" x-cloak>
                <template x-for="(file, index) in files" :key="file.id">
                    <li class="grid grid-cols-[88px_minmax(0,1fr)_44px] items-center gap-3 rounded-xl border border-line bg-surface p-2.5 sm:grid-cols-[112px_minmax(0,1fr)_230px_44px] sm:gap-4">
                        <img :src="file.url" :alt="'{{ __('submissions.form.preview_of', ['name' => '']) }}' + file.name" class="h-16 w-full rounded-lg bg-raised object-cover">
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <span class="truncate font-medium" x-text="file.name"></span>
                            <span class="text-[13px] text-ink-3"><span x-text="file.size"></span><span x-show="file.dimensions"> · <span x-text="file.dimensions"></span></span></span>
                        </div>
                        <div class="col-span-3 flex flex-col gap-1 sm:col-span-1">
                            <label :for="'type-' + file.id" class="text-xs text-ink-label">{{ __('submissions.form.type') }}</label>
                            <select :id="'type-' + file.id" name="image_types[]" x-model="file.kind" class="min-h-10 rounded-lg border border-[#33404A] bg-page px-2.5 text-sm text-ink">
                                @foreach (\App\Enums\EvidenceKind::cases() as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="button" :data-id="file.id" @click="remove" class="row-start-1 flex size-11 cursor-pointer items-center justify-center rounded-[10px] border border-[#33404A] bg-transparent text-ink-2 hover:text-ink sm:col-start-4" :aria-label="'{{ __('submissions.form.remove', ['name' => '']) }}' + file.name">
                            <x-icon.x />
                        </button>
                    </li>
                </template>
            </ul>
            <noscript><p class="m-0 text-[13px] text-ink-3">{{ __('submissions.form.no_js_types') }}</p></noscript>
        </section>

        {{-- 4: note --}}
        <section aria-labelledby="step-note" class="flex flex-col gap-2.5" x-data="charCounter">
            <h2 id="step-note" class="m-0 flex items-center gap-2.5 text-base font-semibold">
                <span class="{{ $stepNumber }}">4</span>
                <label for="player_note">{{ __('submissions.form.step_note') }} <span class="font-normal text-ink-label">{{ __('app.optional') }}</span></label>
            </h2>
            <textarea id="player_note" name="player_note" rows="4" maxlength="{{ config('genetic.player_note_max') }}" x-ref="field" @input="update"
                      placeholder="{{ __('submissions.form.note_placeholder') }}"
                      class="resize-y rounded-xl border border-[#33404A] bg-sunken px-3.5 py-3 text-ink placeholder:text-ink-3 focus:border-ok focus:outline-none">{{ old('player_note') }}</textarea>
            <span class="self-end text-xs text-ink-3"><span x-text="count">0</span> / {{ config('genetic.player_note_max') }}</span>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line-head pt-5">
            <span class="max-w-[560px] text-sm text-ink-2">{{ __('submissions.form.after_send') }}</span>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-12 items-center px-4 font-medium text-ink-2 no-underline hover:text-ink">{{ __('app.cancel') }}</a>
                <button type="submit" class="btn-primary min-h-12 rounded-[10px] px-6" :disabled="submitting">{{ __('submissions.form.submit') }}</button>
            </div>
        </div>
    </form>
</x-layout>
