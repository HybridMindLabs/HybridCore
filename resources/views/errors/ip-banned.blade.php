@extends('errors.layout')

@section('code', '403')
@section('title', 'IP Banned')
@section('message', 'Access to this site has been restricted for your IP address.')
@section('accent-from', '#f87171')
@section('accent-to', '#ef4444')

@if($reason)
    @section('reason', $reason)
@endif
