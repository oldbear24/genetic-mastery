@props(['status'])
@php
    $class = match ($status) {
        \App\Enums\SubmissionStatus::Pending => 'badge-pending',
        \App\Enums\SubmissionStatus::Verified => 'badge-verified',
        \App\Enums\SubmissionStatus::Rejected, \App\Enums\SubmissionStatus::Revoked => 'badge-rejected',
        default => 'badge-muted',
    };
@endphp
<span {{ $attributes->merge(['class' => $class]) }}>{{ $status->label() }}</span>
