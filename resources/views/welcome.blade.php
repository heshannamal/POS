@extends('layouts.auth')
@section('title','Your business, connected')
@section('content')
    <span class="overline">WELCOME TO POS CLOUD</span>
    <h2>Everything for your business, in one place.</h2>
    <p class="muted">Start with a real business workspace. When you subscribe, your products, employees and settings stay exactly where you left them.</p>
    <div class="button-stack">
        @auth
            @if(auth()->user()->is_platform_admin)
                <a class="btn btn-primary" href="{{ route('platform.dashboard') }}">Open platform console <span>→</span></a>
            @else
                <a class="btn btn-primary" href="{{ route('onboarding.create') }}">Go to business setup <span>→</span></a>
            @endif
        @else
            <a class="btn btn-primary" href="{{ route('register') }}">Start your free trial <span>→</span></a>
            <a class="btn btn-outline" href="{{ route('login') }}">Sign in to your account</a>
        @endauth
    </div>
@endsection
