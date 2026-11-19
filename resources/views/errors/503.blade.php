@extends('errors.layout')

@php
    // A core update in progress gets its own wording and a page that reloads itself.
    $updating = rescue(fn () => (bool) (\Illuminate\Support\Facades\Cache::get(\App\Services\CoreUpdater::PROGRESS_KEY)['running'] ?? false), false, false);
@endphp

@section('code', '503')
@section('title', $updating ? 'Updating' : 'Under Maintenance')
@if ($updating)
    @section('head')
        <meta http-equiv="refresh" content="20">
    @endsection
@endif
@section('message')
@if ($updating)
We're installing an update. The site is usually back within a few minutes — this page reloads by itself.
@else
{{ isset($exception) && $exception->getMessage() !== '' ? $exception->getMessage() : "We're performing scheduled maintenance and will be back shortly. Thank you for your patience." }}
@endif
@endsection
@section('accent-from', '#a78bfa')
@section('accent-to', '#8b5cf6')
@section('action', 'Try again')
