@extends('errors.layout')

@section('code', '404')
@section('title', 'Page Not Found')
@section('message', "The page you're looking for doesn't exist or may have been moved.")
@section('accent-from', '#60a5fa')
@section('accent-to', '#3b82f6')
@section('secondary-action', 'Go back')
@section('secondary-href', 'javascript:history.back()')
