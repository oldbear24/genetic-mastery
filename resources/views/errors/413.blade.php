@extends('errors.minimal')

@section('code', '413')
@section('title', __('app.errors.413_title'))
@section('message', __('app.errors.413_text', ['max' => config('genetic.upload.max_total_mb')]))
