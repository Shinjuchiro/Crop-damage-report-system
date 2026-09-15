@extends('layouts.auth')

@section('title', 'Login')

@section('content')

{{--
    Login.

    This file holds TWO separate designs, one after the other, and only one is
    ever visible:

        MOBILE   (lg:hidden)  the phone mockup: green banner, overlapping
                              white card, big fields, the New Farmer and
                              Need help cards.
        DESKTOP  (hidden lg:*) the split panel: green side with the seal in
                              concentric rings, plain form on the right.

    They are kept apart on purpose. When they shared markup, a change meant
    for the phone leaked onto the desktop, which is exactly what happened
    before. Duplicating a little markup is worth not breaking one while
    fixing the other.

    Both post to the same route with the same field names, so the controller
    does not know or care which one was used.
--}}

{{-- ===================================================================
     MOBILE
=================================================================== --}}
<div class="lg:hidden">

    {{-- Green banner --}}
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

    {{-- White card pulled up over the banner --}}
    <div class="relative -mt-10 min-h-[60vh] rounded-t-3xl bg-card px-5 pb-10 pt-7">

        <div class="mb-6 flex items-center gap-4">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-accent
                         text-accent-foreground">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="3.5"/><path d="M5.5 20v-.5a6.5 6.5 0 0113 0v.5"/>
                </svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-[#0d3d1b] dark:text-foreground">Welcome Back!</h2>
                <p class="text-sm text-muted-foreground">Login to continue to your account</p>
            </div>
        </div>

        <h3 class="text-xl font-bold">Login</h3>
        <p class="mb-5 mt-0.5 text-sm text-muted-foreground">
            Please enter your credentials to access the system.
        </p>

        @if ($errors->any())
            <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        @if (session('status'))
            <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="3.2"/><path d="M5.5 20v-.5a6.5 6.5 0 0113 0v.5"/>
                    </svg>
                </span>
                <input type="text" name="login" value="{{ old('login') }}" required autocomplete="username"
                       placeholder="Username or Email"
                       class="h-14 w-full rounded-xl border-2 border-[#166534]/70 bg-card pl-12 pr-4 text-base
                              placeholder:text-muted-foreground focus:border-[#166534]
                              dark:border-input dark:focus:border-primary">
            </div>

            {{-- Show and hide, because typing a password blind on a phone
                 keyboard outdoors is genuinely hard. --}}
            <div class="relative" x-data="{ show: false }">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 118 0v3.5"/>
                    </svg>
                </span>
                <input :type="show ? 'text' : 'password'" name="password" required
                       autocomplete="current-password" placeholder="Password"
                       class="h-14 w-full rounded-xl border border-input bg-card pl-12 pr-12 text-base
                              placeholder:text-muted-foreground focus:border-primary">
                <button type="button" @click="show = ! show"
                        :aria-label="show ? 'Hide password' : 'Show password'"
                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-muted-foreground">
                    <svg x-show="! show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/>
                    </svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 3l18 18M10.6 10.7a2.8 2.8 0 003.8 3.8M6.5 6.7C3.9 8.3 2 12 2 12s3.6 6.5 10 6.5c1.7 0 3.2-.5 4.5-1.1M19.5 15.5C21.2 14 22 12 22 12s-3.6-6.5-10-6.5c-.7 0-1.3.1-1.9.2"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                           class="h-5 w-5 rounded border-input text-primary focus:ring-ring">
                    Remember me
                </label>
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-[#166534] dark:text-primary">
                    Forgot password?
                </a>
            </div>

            <button type="submit"
                    class="flex h-14 w-full items-center justify-center gap-3 rounded-xl bg-[#166534]
                           text-base font-bold text-white transition active:scale-[0.99]
                           dark:bg-primary dark:text-primary-foreground">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 118 0v3.5"/>
                </svg>
                LOGIN
            </button>
        </form>

        <div class="my-5 flex items-center gap-4">
            <span class="h-px flex-1 bg-border"></span>
            <span class="text-sm font-semibold text-muted-foreground">or</span>
            <span class="h-px flex-1 bg-border"></span>
        </div>

        <a href="{{ route('google.redirect') }}"
           class="flex h-14 w-full items-center justify-center gap-3 rounded-xl border border-input bg-card
                  text-base font-semibold transition hover:bg-accent">
            <x-google-mark />
            Continue with Google
        </a>

        <div class="mt-6 flex items-start gap-4 rounded-xl bg-accent/60 p-4 dark:bg-muted">
            <svg class="mt-0.5 h-10 w-10 shrink-0 text-[#166534] dark:text-primary" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                 viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 9.5h16M6 9.5c0-3.3 2.7-6 6-6s6 2.7 6 6M9 13v7.5h6V13M7 20.5h10"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">New Farmer?</p>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">
                    Create a farmer account to report crop damage and access our services.
                </p>
            </div>
            <div class="shrink-0 text-center">
                <a href="{{ route('register') }}"
                   class="inline-flex items-center rounded-lg border border-[#166534] px-4 py-2 text-sm
                          font-semibold text-[#166534] dark:border-primary dark:text-primary">
                    REGISTER
                </a>
                <p class="mt-1 text-xs text-muted-foreground">For farmers only</p>
            </div>
        </div>

        <div class="mt-3 flex items-center gap-4 rounded-xl bg-accent/60 p-4 dark:bg-muted">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0d3d1b] text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M4 13v-1a8 8 0 1116 0v1M4 13h2.5v5H5a1 1 0 01-1-1v-4zm16 0h-2.5v5H19a1 1 0 001-1v-4z"/>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">Need help?</p>
                <p class="mt-0.5 text-sm text-muted-foreground">Contact our support team anytime you need us.</p>
            </div>
            <a href="mailto:mao@tanza.gov.ph"
               class="shrink-0 rounded-lg border border-[#166534] px-3 py-2 text-xs font-semibold
                      text-[#166534] dark:border-primary dark:text-primary">
                CONTACT ADMIN
            </a>
        </div>

        <p class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
            <svg class="h-4 w-4 text-[#166534] dark:text-primary" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M12 3l7 3v5.5c0 4.3-2.9 8.3-7 9.5-4.1-1.2-7-5.2-7-9.5V6l7-3z"/>
            </svg>
            Your data is protected with secure encryption.
        </p>
        <p class="mt-2 text-center text-xs leading-relaxed text-muted-foreground">
            &copy; {{ date('Y') }} Farmers Information and Technology Services Center.<br>All rights reserved.
        </p>
    </div>
</div>

{{-- ===================================================================
     DESKTOP
     A full-bleed photo of the FITSC office, tinted green, with the form
     floating in a single centered card - the seal sits half over its top
     edge instead of living in its own panel. Nothing from the phone
     layout above appears here.
=================================================================== --}}
<div class="relative hidden min-h-screen overflow-hidden lg:flex lg:items-center lg:justify-center lg:px-10 lg:py-14">

    <img src="{{ asset('images/fitsc-office.jpg') }}" alt=""
         class="absolute inset-0 h-full w-full object-cover" aria-hidden="true">
    <div class="absolute inset-0 bg-[#0d3d1b]/70" aria-hidden="true"></div>

    {{-- Form card --}}
    <div class="relative w-full max-w-lg">

        <div class="absolute -top-9 left-1/2 -translate-x-1/2">
            <span class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-white p-2 shadow-lg ring-4 ring-white/30">
                <img src="{{ asset('images/tanza-seal.png') }}"
                     alt="Seal of the Municipality of Tanza, Cavite" class="h-full w-full">
            </span>
        </div>

        <div class="rounded-2xl bg-card px-12 pb-10 pt-14 shadow-2xl">

            <h2 class="text-center text-3xl font-bold text-[#0d3d1b] dark:text-foreground">Welcome Back!</h2>
            <p class="mt-1 text-center text-sm text-muted-foreground">
                Please enter your credentials to access the system.
            </p>

            @if ($errors->any())
                <x-ui.alert variant="destructive" class="mt-5">{{ $errors->first() }}</x-ui.alert>
            @endif
            @if (session('status'))
                <x-ui.alert variant="success" class="mt-5">{{ session('status') }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="login-desktop" class="block text-sm font-medium">Username or Email</label>
                    <input id="login-desktop" type="text" name="login" value="{{ old('login') }}" required
                           autofocus autocomplete="username"
                           class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-sm
                                  focus:border-primary">
                </div>

                <div class="space-y-1.5">
                    <label for="password-desktop" class="block text-sm font-medium">Password</label>
                    {{-- Same show/hide toggle as the mobile layout above, just
                         sized for the compact desktop field. --}}
                    <div class="relative" x-data="{ show: false }">
                        <input id="password-desktop" :type="show ? 'text' : 'password'" name="password" required
                               autocomplete="current-password"
                               class="h-11 w-full rounded-lg border border-input bg-card px-3.5 pr-10 text-sm
                                      focus:border-primary">
                        <button type="button" @click="show = ! show"
                                :aria-label="show ? 'Hide password' : 'Show password'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground">
                            <svg x-show="! show" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/>
                            </svg>
                            <svg x-show="show" x-cloak class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 3l18 18M10.6 10.7a2.8 2.8 0 003.8 3.8M6.5 6.7C3.9 8.3 2 12 2 12s3.6 6.5 10 6.5c1.7 0 3.2-.5 4.5-1.1M19.5 15.5C21.2 14 22 12 22 12s-3.6-6.5-10-6.5c-.7 0-1.3.1-1.9.2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 pt-1">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                               class="h-4 w-4 rounded border-input text-primary focus:ring-ring">
                        Remember me
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-[#166534] dark:text-primary">
                        Forgot password?
                    </a>
                </div>

                <button type="submit"
                        class="h-11 w-full rounded-lg bg-[#166534] text-sm font-semibold text-white
                               transition hover:brightness-110 dark:bg-primary dark:text-primary-foreground">
                    Login
                </button>
            </form>

            <div class="my-5 flex items-center gap-4">
                <span class="h-px flex-1 bg-border"></span>
                <span class="text-xs font-medium text-muted-foreground">or</span>
                <span class="h-px flex-1 bg-border"></span>
            </div>

            <a href="{{ route('google.redirect') }}"
               class="flex h-11 w-full items-center justify-center gap-3 rounded-lg border border-input
                      bg-card text-sm font-medium transition hover:bg-accent">
                <x-google-mark />
                Continue with Google
            </a>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                New farmer?
                <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">
                    Create an account
                </a>
            </p>

            <p class="mt-8 text-center text-xs text-muted-foreground">
                &copy; {{ date('Y') }} Farmers Information and Technology Services Center.
            </p>
        </div>
    </div>
</div>
@endsection
