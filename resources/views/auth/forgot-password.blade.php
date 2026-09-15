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
        <img src="{{ asset('images/fitsc-office.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover opacity-40" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0d3d1b]/80 to-[#0d3d1b]/95" aria-hidden="true"></div>

        {{-- Extra bottom padding (not just pt) is what keeps this text clear
             of the seal overlapping the seam below - see auth.login's
             matching banner comment. --}}
        <div class="relative px-5 pb-24 pt-8 text-center">
            <p class="text-lg font-bold leading-tight">
                Farmers Information and<br>Technology Services Center
            </p>
            <p class="mt-1 text-sm font-medium text-[#7ddc8f]">Municipality of Tanza</p>
        </div>
    </div>

    {{-- White card pulled up over the banner. The seal overlaps the seam
         between the two, same treatment as auth.login's mobile view. --}}
    <div class="relative -mt-10 min-h-[60vh] rounded-t-3xl bg-card px-5 pb-10 pt-12">

        <div class="absolute -top-9 left-1/2 -translate-x-1/2">
            <span class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-white p-2 shadow-lg ring-4 ring-white/30">
                <img src="{{ asset('images/tanza-seal.png') }}"
                     alt="Seal of the Municipality of Tanza, Cavite" class="h-full w-full">
            </span>
        </div>

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
                <p class="text-sm text-muted-foreground">Choose how you'd like to reset it</p>
            </div>
        </div>

        {{-- Two ways in, picked with a tab switch rather than two separate
             pages - a farmer who doesn't have easy access to their email can
             still get back into their account with their phone. --}}
        <div x-data="{ method: 'email' }">

            <div class="mb-5 grid grid-cols-2 gap-1 rounded-xl bg-accent/60 p-1 dark:bg-muted">
                <button type="button" @click="method = 'email'"
                        :class="method === 'email' ? 'bg-card text-[#166534] shadow dark:text-primary' : 'text-muted-foreground'"
                        class="rounded-lg py-2.5 text-sm font-semibold transition">
                    Email
                </button>
                <button type="button" @click="method = 'sms'"
                        :class="method === 'sms' ? 'bg-card text-[#166534] shadow dark:text-primary' : 'text-muted-foreground'"
                        class="rounded-lg py-2.5 text-sm font-semibold transition">
                    Text Message
                </button>
            </div>

            @if ($errors->any())
                <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
            @endif
            @if (session('status'))
                <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
            @endif

            <div x-show="method === 'email'" x-cloak>
                <h3 class="text-xl font-bold">Reset Your Password</h3>
                <p class="mb-5 mt-0.5 text-sm text-muted-foreground">
                    Enter the email address on your account and we'll send you a link to reset your password.
                </p>

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
            </div>

            <div x-show="method === 'sms'" x-cloak>
                <h3 class="text-xl font-bold">Text Me a Code</h3>
                <p class="mb-5 mt-0.5 text-sm text-muted-foreground">
                    Enter the phone number on your account and we'll text you a 6-digit code to reset your password.
                </p>

                <form method="POST" action="{{ route('password.otp.send') }}" class="space-y-4">
                    @csrf

                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6
                                         19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362
                                         1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0
                                         012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        </span>
                        <input type="tel" name="phone_number" value="{{ old('phone_number') }}" required
                               inputmode="numeric" autocomplete="tel" placeholder="09XXXXXXXXX"
                               class="h-14 w-full rounded-xl border-2 border-[#166534]/70 bg-card pl-12 pr-4 text-base
                                      placeholder:text-muted-foreground focus:border-[#166534]
                                      dark:border-input dark:focus:border-primary">
                    </div>

                    <button type="submit"
                            class="flex h-14 w-full items-center justify-center gap-3 rounded-xl bg-[#166534]
                                   text-base font-bold text-white transition active:scale-[0.99]
                                   dark:bg-primary dark:text-primary-foreground">
                        SEND CODE
                    </button>
                </form>
            </div>
        </div>

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
     Same full-bleed photo + centered floating card as auth.login's desktop
     block - see that file's comment for why the two designs are kept apart
     rather than shared.
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

            <h2 class="text-center text-3xl font-bold text-[#0d3d1b] dark:text-foreground">Forgot Password?</h2>
            <p class="mt-1 text-center text-sm text-muted-foreground">Choose how you'd like to reset it.</p>

            <div x-data="{ method: 'email' }" class="mt-6">

                <div class="mb-5 grid grid-cols-2 gap-1 rounded-xl bg-accent/60 p-1 dark:bg-muted">
                    <button type="button" @click="method = 'email'"
                            :class="method === 'email' ? 'bg-card text-[#166534] shadow-sm dark:text-primary' : 'text-muted-foreground'"
                            class="rounded-lg py-2 text-sm font-semibold transition">
                        Email
                    </button>
                    <button type="button" @click="method = 'sms'"
                            :class="method === 'sms' ? 'bg-card text-[#166534] shadow-sm dark:text-primary' : 'text-muted-foreground'"
                            class="rounded-lg py-2 text-sm font-semibold transition">
                        Text Message
                    </button>
                </div>

                @if ($errors->any())
                    <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
                @endif
                @if (session('status'))
                    <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif

                <div x-show="method === 'email'" x-cloak>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Enter the email address on your account and we'll send you a link to reset your password.
                    </p>

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
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
                </div>

                <div x-show="method === 'sms'" x-cloak>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Enter the phone number on your account and we'll text you a 6-digit code to reset your password.
                    </p>

                    <form method="POST" action="{{ route('password.otp.send') }}" class="space-y-4">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="phone-desktop" class="block text-sm font-medium">Phone number</label>
                            <input id="phone-desktop" type="tel" name="phone_number" value="{{ old('phone_number') }}"
                                   required inputmode="numeric" autocomplete="tel" placeholder="09XXXXXXXXX"
                                   class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-sm
                                          focus:border-primary">
                        </div>

                        <button type="submit"
                                class="h-11 w-full rounded-lg bg-[#166534] text-sm font-semibold text-white
                                       transition hover:brightness-110 dark:bg-primary dark:text-primary-foreground">
                            Send Code
                        </button>
                    </form>
                </div>
            </div>

            <p class="mt-6 text-center text-sm">
                <a href="{{ route('login') }}" class="font-semibold text-[#166534] dark:text-primary">
                    &larr; Back to Login
                </a>
            </p>
        </div>
    </div>
</div>
@endsection
