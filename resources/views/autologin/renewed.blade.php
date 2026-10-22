@extends('identity-contracts::autologin.layout')

@section('title', __('identity-contracts::autologin.renewed.title'))

@section('content')
    <h1>{{ __('identity-contracts::autologin.renewed.title') }}</h1>
    <p>{{ __('identity-contracts::autologin.renewed.text') }}</p>
@endsection
