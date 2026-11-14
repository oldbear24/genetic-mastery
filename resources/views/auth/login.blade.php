<x-layout :title="__('auth.login.title')" width="max-w-[560px]" glow>
    <div class="card flex flex-col gap-5 p-7 sm:p-9">
        <x-icon.dna :size="40" />
        <div class="flex flex-col gap-2">
            <h1 class="m-0 font-display text-[28px] font-semibold">{{ __('auth.login.heading') }}</h1>
            <p class="m-0 text-ink-2">{{ __('auth.login.text') }}</p>
        </div>
        <a href="{{ route('auth.discord') }}" class="btn-light self-start">{{ __('auth.login.button') }}</a>
        <p class="m-0 border-t border-line-soft pt-4 text-sm text-ink-3">
            {{ __('auth.login.public_note') }}
            <a href="{{ route('home') }}" class="link-underline">{{ __('app.nav.milestones') }}</a>
        </p>
    </div>
</x-layout>
