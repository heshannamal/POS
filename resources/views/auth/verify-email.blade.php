@extends('layouts.auth')
@section('title','Verify email')
@section('content')
    <span class="overline">SECURE YOUR ACCOUNT</span>
    <h2>Verify your email address.</h2>
    <p class="muted">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Follow that link before creating your business.</p>
    @if(auth()->user()->hasVerifiedEmail())
        <a href="{{ route('onboarding.create') }}" class="btn btn-primary">Continue to business setup →</a>
    @else
        <form method="POST" action="{{ route('verification.send') }}">@csrf
            <button class="btn btn-primary" type="submit">Resend verification email</button>
        </form>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="spaced-form">@csrf
        <button type="submit" class="text-button">Sign out</button>
    </form>
@endsection
