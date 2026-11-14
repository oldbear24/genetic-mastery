{{-- Error pages do not touch the database or the session user. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>@yield('title') · {{ __('app.title_suffix') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[radial-gradient(ellipse_70%_460px_at_50%_0%,rgba(69,209,158,0.09),rgba(14,18,20,0))] bg-no-repeat px-4">
<main class="card flex w-full max-w-[520px] flex-col gap-4 p-8">
    <span class="flex items-center gap-3">
        <x-icon.dna />
        <span class="font-mono text-sm text-ink-3">@yield('code')</span>
    </span>
    <h1 class="m-0 font-display text-[28px] font-semibold">@yield('title')</h1>
    <p class="m-0 text-ink-2">@yield('message')</p>
    <a href="{{ url('/') }}" class="btn-ghost self-start">{{ __('app.errors.home') }}</a>
</main>
</body>
</html>
