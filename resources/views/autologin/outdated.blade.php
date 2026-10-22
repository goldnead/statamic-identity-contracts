@extends('identity-contracts::autologin.layout')

@section('title', __('identity-contracts::autologin.outdated.title'))

@section('content')
    <h1>{{ __('identity-contracts::autologin.outdated.title') }}</h1>
    <p>{{ __('identity-contracts::autologin.outdated.text') }}</p>
    <a class="btn" href="{{ $loginUrl }}">{{ __('identity-contracts::autologin.outdated.login') }}</a>
@endsection
