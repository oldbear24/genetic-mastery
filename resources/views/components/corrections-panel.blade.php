{{-- Corrections for a decided submission (OPR-2 to OPR-5). Server checks every action (SEC-2). --}}
@props(['submission', 'admins'])
@php
    $S = \App\Enums\SubmissionStatus::class;
    $s = $submission;
    $canCorrect = auth()->user()->can('correct', $s);
@endphp
<section id="corrections" aria-labelledby="corrections-title" class="card flex flex-col gap-5 p-6">
    <h3 id="corrections-title" class="m-0 font-display text-xl font-semibold">{{ __('corrections.title') }}</h3>

    @if (! $canCorrect && in_array($s->status, [$S::Verified, $S::Rejected, $S::Revoked], true))
        <div class="alert-warning">{{ __('corrections.own_claim') }}</div>
    @endif

    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-5">
        @if ($canCorrect && $s->status === $S::Verified)
            <form method="POST" action="{{ route('admin.submissions.revoke', $s) }}" class="flex flex-col gap-2.5" x-data="submitOnce" @submit="submit">
                @csrf
                <h4 class="m-0 text-base font-semibold">{{ __('corrections.revoke') }}</h4>
                <label for="revoke_reason" class="text-[13px] text-ink-label">{{ __('corrections.revoke_reason') }}</label>
                <textarea id="revoke_reason" name="revoke_reason" rows="3" required maxlength="1000" placeholder="{{ __('corrections.revoke_placeholder') }}"
                          class="resize-y rounded-[10px] border border-[#33404A] bg-page px-3 py-2.5 text-sm text-ink placeholder:text-ink-3 focus:border-ok focus:outline-none">{{ old('revoke_reason') }}</textarea>
                @error('revoke_reason')<span class="text-[13px] text-bad-text">{{ $message }}</span>@enderror
                <span class="text-[13px] text-ink-2">{{ __('corrections.revoke_effect', ['indo' => config('genetic.indo.per_milestone')]) }}</span>
                <button type="submit" class="btn-danger mt-auto min-h-11 rounded-[10px]" :disabled="submitting">{{ __('corrections.revoke') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.submissions.verifier', $s) }}" class="flex flex-col gap-2.5">
                @csrf
                <h4 class="m-0 text-base font-semibold">{{ __('corrections.change_verifier') }}</h4>
                <label for="reviewer_id" class="text-[13px] text-ink-label">{{ __('corrections.verifier') }}</label>
                <select id="reviewer_id" name="reviewer_id" class="min-h-11 rounded-[10px] border border-[#33404A] bg-page px-3 text-sm text-ink">
                    @foreach ($admins as $admin)
                        <option value="{{ $admin->id }}" @selected($admin->id === $s->reviewed_by)>{{ $admin->display_name }}</option>
                    @endforeach
                </select>
                @error('reviewer_id')<span class="text-[13px] text-bad-text">{{ $message }}</span>@enderror
                <button type="submit" class="btn-ghost mt-auto min-h-11 rounded-[10px]">{{ __('corrections.save_verifier') }}</button>
            </form>
        @endif

        @if ($canCorrect && in_array($s->status, [$S::Rejected, $S::Revoked], true))
            <form method="POST" action="{{ route('admin.submissions.reopen', $s) }}" class="flex flex-col gap-2.5">
                @csrf
                <h4 class="m-0 text-base font-semibold">{{ __('corrections.reopen') }}</h4>
                <span class="text-[13px] text-ink-2">{{ __('corrections.reopen_effect') }}</span>
                <button type="submit" class="btn-ghost mt-auto min-h-11 rounded-[10px]">{{ __('corrections.reopen') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.submissions.notes', $s) }}" class="flex flex-col gap-2.5">
            @csrf
            <h4 class="m-0 text-base font-semibold">{{ __('corrections.add_note') }}</h4>
            <label for="note_body" class="text-[13px] text-ink-label">{{ __('corrections.note_body') }}</label>
            <textarea id="note_body" name="body" rows="3" required maxlength="1000"
                      class="resize-y rounded-[10px] border border-[#33404A] bg-page px-3 py-2.5 text-sm text-ink focus:border-ok focus:outline-none">{{ old('body') }}</textarea>
            @error('body')<span class="text-[13px] text-bad-text">{{ $message }}</span>@enderror
            <fieldset class="m-0 flex flex-col gap-1.5 border-0 p-0 text-sm">
                <legend class="mb-1 p-0 text-[13px] text-ink-label">{{ __('corrections.note_visibility') }}</legend>
                <label class="flex min-h-8 items-center gap-2"><input type="radio" name="visibility" value="public" checked class="accent-[#45D19E]"> {{ __('corrections.note_public') }}</label>
                <label class="flex min-h-8 items-center gap-2"><input type="radio" name="visibility" value="internal" class="accent-[#F2B24C]"> {{ __('corrections.note_internal') }}</label>
            </fieldset>
            <button type="submit" class="btn-ghost mt-auto min-h-11 rounded-[10px]">{{ __('corrections.save_note') }}</button>
        </form>
    </div>
</section>
