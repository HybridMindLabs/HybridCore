@extends('errors.layout')

@section('code', '503')
@section('title', 'Under Maintenance')
@section('message')
{{ isset($exception) && $exception->getMessage() !== '' ? $exception->getMessage() : "We're performing scheduled maintenance and will be back shortly. Thank you for your patience." }}
@endsection
@section('accent-from', '#a78bfa')
@section('accent-to', '#8b5cf6')
@section('action', 'Try again')
