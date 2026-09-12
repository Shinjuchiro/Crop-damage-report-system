@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')

{{--
    Forgot Password.

    Same two-design split as auth.login (see that file's comment for why):
    a mobile phone layout and a separate desktop split panel. Only the form
    itself differs from login - one email field, one button - the
    surrounding shell (banner, seal, card) is identical on purpose so this
    reads as the same system, not a different page that got lost.
--}}

{{-- ===================================================================
     MOBILE
=================================================================== --}}
<div class="lg:hidden">

    <div class="relative overflow-hidden bg-[#0d3d1b] text-white">
        <img src="{{ asset('images/farm-aerial.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover opacity-25" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0d3d1b]/85 to-[#0d3d1b]/95" aria-hidden="true"></div>

        <div class="relative flex items-center gap-4 px-5 pb-16 pt-8">
            <img src="{{ asset('images/tanza-seal.png') }}"
                 alt="Seal of the Municipality of Tanza, Cavite" class="h-16 w-16 shrink-0">
            <div class="min-w-0">
                <p class="text-lg font-bold leading-tight">
                    Farmers Information and<br>Technology Services Center
                </p>
                <p class="mt-1 text-sm font-medium text-[#7ddc8f]">Municipality of Tanza</p>
            </div>
        </div>
    </div>

    <div class="relative -mt-10 min-h-[60vh] rounded-t-3xl bg-card px-5 pb-10 pt-7">

        <div class="mb-6 flex items-center gap-4">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-accent
                         text-accent-foreground">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 118 0v3.5"/>
                </svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-[#0d3d1b] dark:text-foreground">Forgot Password?</h2>
                <p class="text-sm text-muted-foreground">We'll email you a reset link</p>
            </div>
        </div>

        <h3 class="text-xl font-bold">Reset Your Password</h3>
        <p class="mb-5 mt-0.5 text-sm text-muted-foreground">
            Enter the email address on your account and we'll send you a link to reset your password.
        </p>

        @if ($errors->any())
            <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        @if (session('status'))
            <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>
                    </svg>
                </span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                       placeholder="Email address"
                       class="h-14 w-full rounded-xl border-2 border-[#166534]/70 bg-card pl-12 pr-4 text-base
                              placeholder:text-muted-foreground focus:border-[#166534]
                              dark:border-input dark:focus:border-primary">
            </div>

            <button type="submit"
                    class="flex h-14 w-full items-center justify-center gap-3 rounded-xl bg-[#166534]
                           text-base font-bold text-white transition active:scale-[0.99]
                           dark:bg-primary dark:text-primary-foreground">
                SEND RESET LINK
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="font-semibold text-[#166534] dark:text-primary">
                &larr; Back to Login
            </a>
        </p>

        <p class="mt-8 text-center text-xs leading-relaxed text-muted-foreground">
            &copy; {{ date('Y') }} Farmers Information and Technology Services Center.<br>All rights reserved.
        </p>
    </div>
</div>

{{-- ===================================================================
     DESKTOP
=================================================================== --}}
<div class="hidden min-h-screen lg:grid lg:grid-cols-2">

    <div class="relative overflow-hidden bg-[#0d3d1b] text-white">
        <img src="{{ asset('images/farm-aerial.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover opacity-25" aria-hidden="true">
        <div class="absolute inset-0 bg-[#0d3d1b]/80" aria-hidden="true"></div>

        <div class="relative flex h-full flex-col items-center justify-center px-12 py-16 text-center">
            <div class="relative mb-8 flex h-64 w-64 items-center justify-center">
                <span class="absolute inset-0 rounded-full border border-white/15"></span>
                <span class="absolute inset-6 rounded-full border border-white/20"></span>
                <span class="absolute inset-12 rounded-full border border-white/25"></span>
                <img src="{{ asset('images/tanza-seal.png') }}"
                     alt="Seal of the Municipality of Tanza, Cavite" class="relative h-36 w-36">
            </div>

            <h1 class="text-3xl font-bold leading-tight">
                Farmers Information and<br>Technology Services Center
            </h1>
            <p class="mt-3 text-lg font-medium text-[#7ddc8f]">Municipality of Tanza</p>

            <p class="mt-6 max-w-md text-sm leading-relaxed text-white/75">
                Crop damage reporting and assistance allocation for disaster affected farmers
                in Tanza, Cavite.
            </p>
        </div>
    </div>

    <div class="flex items-center justify-center bg-card px-12 py-12">
        <div class="w-full max-w-sm">

            <h2 class="text-3xl font-bold text-[#0d3d1b] dark:text-foreground">Forgot Password?</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Enter the email address on your account and we'll send you a link to reset your password.
            </p>

            @if ($errors->any())
                <x-ui.alert variant="destructive" class="mt-5">{{ $errors->first() }}</x-ui.alert>
            @endif
            @if (session('status'))
                <x-ui.alert variant="success" class="mt-5">{{ session('status') }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="email-desktop" class="block text-sm font-medium">Email address</label>
                    <input id="email-desktop" type="email" name="email" value="{{ old('email') }}" required
                           autocomplete="email"
                           class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-sm
                                  focus:border-primary">
                </div>

                <button type="submit"
                        class="h-11 w-full rounded-lg bg-[#166534] text-sm font-semibold text-white
                               transition hover:brightness-110 dark:bg-primary dark:text-primary-foreground">
                    Send Reset Link
                </button>
            </form>

            <p class="mt-6 text-center text-sm">
                <a href="{{ route('login') }}" class="font-semibold text-[#166534] dark:text-primary">
                    &larr; Back to Login
                </a>
            </p>
        </div>
    </div>
</div>
@endsection
