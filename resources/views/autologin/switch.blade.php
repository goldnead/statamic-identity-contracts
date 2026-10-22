@extends('identity-contracts::autologin.layout')

@section('title', __('identity-contracts::autologin.switch.title'))

@section('content')
    <h1>{{ __('identity-contracts::autologin.switch.title') }}</h1>
    <p>{{ __('identity-contracts::autologin.switch.text', ['current' => $currentEmail, 'target' => $targetEmail]) }}</p>
    <form method="post" action="{{ $action }}">
        @csrf
        <input type="hidden" name="action" value="switch">
        <button class="btn" type="submit">{{ __('identity-contracts::autologin.switch.confirm', ['target' => $targetEmail]) }}</button>
        <a class="link" href="/">{{ __('identity-contracts::autologin.switch.stay') }}</a>
    </form>
@endsection
