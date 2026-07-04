@extends('errors.layout')

@section('code', '500')
@section('title', 'Something Went Wrong')
@section('message', 'An unexpected error occurred on our side. The team has been notified — please try again in a moment.')
@section('accent-from', '#fb923c')
@section('accent-to', '#f97316')
@section('secondary-action', 'Try again')
@section('secondary-href', 'javascript:location.reload()')
