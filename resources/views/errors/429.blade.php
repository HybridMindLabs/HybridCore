@extends('errors.layout')

@section('code', '429')
@section('title', 'Too Many Requests')
@section('message', "You're sending requests too quickly. Please wait a moment and try again.")
@section('accent-from', '#fbbf24')
@section('accent-to', '#f59e0b')
@section('secondary-action', 'Go back')
@section('secondary-href', 'javascript:history.back()')
