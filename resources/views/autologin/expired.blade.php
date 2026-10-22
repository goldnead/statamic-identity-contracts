@extends('identity-contracts::autologin.layout')

@section('title', __('identity-contracts::autologin.expired.title'))

@section('content')
    <h1>{{ __('identity-contracts::autologin.expired.title') }}</h1>
    <p>{{ __('identity-contracts::autologin.expired.text') }}</p>
    <form method="post" action="{{ $action }}">
        @csrf
        <input type="hidden" name="action" value="renew">
        <button class="btn" type="submit">{{ __('identity-contracts::autologin.expired.renew') }}</button>
        <a class="link" href="{{ $loginUrl }}">{{ __('identity-contracts::autologin.expired.login') }}</a>
    </form>
@endsection
