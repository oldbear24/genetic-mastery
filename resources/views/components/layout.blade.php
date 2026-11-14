@props(['title' => null, 'area' => 'public', 'current' => null, 'glow' => false, 'width' => 'max-w-[1200px]'])
@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $pendingCount = $isAdmin ? \App\Models\Submission::query()->pending()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ $title ? $title.' · '.__('app.title_suffix') : __('app.title_suffix') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['min-h-screen', 'bg-[radial-gradient(ellipse_70%_460px_at_50%_0%,rgba(69,209,158,0.09),rgba(14,18,20,0))] bg-no-repeat' => $glow])>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2 focus:text-page">{{ __('app.skip_to_content') }}</a>

<header class="border-b border-line-head">
    <div class="mx-auto flex {{ $area === 'admin' ? 'max-w-[1320px]' : 'max-w-[1200px]' }} flex-wrap items-center justify-between gap-4 px-4 py-3.5 sm:px-6">
        <a href="{{ $area === 'admin' ? route('admin.queue') : route('home') }}" class="flex items-center gap-3 text-ink no-underline hover:text-ink">
            <x-icon.dna />
            <span class="flex flex-col leading-[1.15]">
                <span class="font-display text-base font-bold tracking-[0.08em]">{{ __('app.brand') }}</span>
                @if ($area === 'admin')
                    <span class="text-xs font-semibold tracking-[0.06em] text-wait-text">{{ __('app.admin_badge') }}</span>
                @else
                    <span class="text-xs text-ink-label">{{ filled(config('genetic.cluster_name')) ? __('app.cluster_line', ['name' => config('genetic.cluster_name')]) : __('app.tagline') }}</span>
                @endif
            </span>
        </a>

        <nav aria-label="{{ $area === 'admin' ? __('app.nav.admin') : __('app.nav.main') }}" class="flex flex-wrap items-center gap-2">
            @if ($area === 'admin')
                <a href="{{ route('admin.queue') }}" class="nav-link flex items-center gap-2" @if ($current === 'queue') aria-current="page" @endif>
                    {{ __('app.nav.review_queue') }}
                    @if ($pendingCount > 0)
                        <span class="rounded-full bg-wait px-[7px] py-px font-mono text-xs font-semibold text-wait-on" aria-label="{{ trans_choice('app.nav.pending_count', $pendingCount) }}">{{ $pendingCount }}</span>
                    @endif
                </a>
                @if (Route::has('admin.submissions'))
                    <a href="{{ route('admin.submissions') }}" class="nav-link" @if ($current === 'submissions') aria-current="page" @endif>{{ __('app.nav.all_submissions') }}</a>
                @endif
                @if (Route::has('admin.audit'))
                    <a href="{{ route('admin.audit') }}" class="nav-link" @if ($current === 'audit') aria-current="page" @endif>{{ __('app.nav.audit_log') }}</a>
                @endif
                <a href="{{ route('home') }}" class="nav-link">{{ __('app.nav.milestones') }}</a>
            @else
                <a href="{{ route('home') }}" class="nav-link" @if ($current === 'milestones') aria-current="page" @endif>{{ __('app.nav.milestones') }}</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="nav-link" @if ($current === 'dashboard') aria-current="page" @endif>{{ __('app.nav.dashboard') }}</a>
                @endauth
                <a href="{{ route('leaderboard') }}" class="nav-link" @if ($current === 'leaderboard') aria-current="page" @endif>{{ __('app.nav.leaderboard') }}</a>
                @if ($isAdmin && Route::has('admin.queue'))
                    <a href="{{ route('admin.queue') }}" class="nav-link flex items-center gap-2">
                        {{ __('app.nav.review_queue') }}
                        @if ($pendingCount > 0)
                            <span class="rounded-full bg-wait px-[7px] py-px font-mono text-xs font-semibold text-wait-on" aria-label="{{ trans_choice('app.nav.pending_count', $pendingCount) }}">{{ $pendingCount }}</span>
                        @endif
                    </a>
                @endif
            @endif

            @auth
                <span class="ml-2 flex items-center gap-2.5 border-l border-line py-1 pr-1 pl-3">
                    <x-avatar :user="$user" :size="34" />
                    <span class="font-medium">{{ $user->display_name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 cursor-pointer rounded-lg border border-line bg-transparent px-3 text-sm text-ink-2 hover:text-ink">{{ __('app.nav.sign_out') }}</button>
                    </form>
                </span>
            @else
                <a href="{{ route('auth.discord') }}" class="btn-light">{{ __('app.nav.sign_in') }}</a>
            @endauth
        </nav>
    </div>
</header>

<main id="main" {{ $attributes->merge(['class' => "mx-auto $width px-4 pt-9 pb-18 sm:px-6"]) }}>
    @if (session('error'))
        <div class="alert-error mb-6" role="alert">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="alert-success mb-6" role="status">{{ session('status') }}</div>
    @endif

    {{ $slot }}
</main>
</body>
</html>
