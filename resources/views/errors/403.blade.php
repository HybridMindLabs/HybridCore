@extends('errors.layout')

@section('code', '403')
@section('title', 'Access Denied')
@section('message', "You don't have permission to view this page. If you believe this is a mistake, please contact an administrator.")
@section('accent-from', '#f87171')
@section('accent-to', '#ef4444')
@section('secondary-action', 'Go back')
@section('secondary-href', 'javascript:history.back()')
