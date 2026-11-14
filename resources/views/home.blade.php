<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.name') }}</title>
</head>
<body>
    <h1>GENETIC MASTERY</h1>
    <p>{{ __('app.skeleton_notice', ['milestones' => trans_choice('app.milestones_count', $milestoneCount)]) }}</p>
</body>
</html>
