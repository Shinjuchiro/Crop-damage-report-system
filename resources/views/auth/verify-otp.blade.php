@extends('layouts.auth')

@section('title', 'Verify Code')

@section('content')

{{--
    Verify Code - the second screen of the SMS side of Forgot Password
    (auth.forgot-password's "Text Message" tab -> PasswordResetController::
    sendOtp() -> here -> verifyOtpAndReset()). Same mobile/desktop split
    shell as login/forgot-password/reset-password - see auth.login's comment
    for why the two layouts are kept fully separate rather than shared.

    Code entry and the new password are one form, one submit: there is no
    separate "code confirmed" step to track server-side, which keeps
    App\Models\PasswordResetOtp's job to exactly one thing - check the code
    matches, isn't expired, and isn't locked out - the same moment the new
    password is set.
--}}

@php
    // Masked for reassurance ("we sent it to a number ending in ...") without
    // showing the farmer's full number back to them on screen.
    $masked = strlen($phone) > 4
        ? str_repeat('•', strlen($phone) - 4) . substr($phone, -4)
        : $phone;
@endphp

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
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6
                             19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362
                             1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0
                             012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-[#0d3d1b] dark:text-foreground">Verify Code</h2>
                <p class="text-sm text-muted-foreground">Sent to {{ $masked }}</p>
            </div>
        </div>

        @if ($errors->any())
            <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif
        @if (session('status'))
            <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('password.otp.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="phone_number" value="{{ old('phone_number', $phone) }}">

            <div>
                <input type="text" name="code" value="{{ old('code') }}" required
                       inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                       placeholder="6-digit code"
                       class="h-14 w-full rounded-xl border-2 border-[#166534]/70 bg-card px-4 text-center
                              text-2xl font-bold tracking-[0.5em] placeholder:text-base placeholder:font-normal
                              placeholder:tracking-normal placeholder:text-muted-foreground
                              focus:border-[#166534] dark:border-input dark:focus:border-primary">
            </div>

            <div class="relative" x-data="{ show: false }">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 118 0v3.5"/>
                    </svg>
                </span>
                <input :type="show ? 'text' : 'password'" name="password" required
                       autocomplete="new-password" placeholder="New password"
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

            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 118 0v3.5"/>
                    </svg>
                </span>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       placeholder="Confirm new password"
                       class="h-14 w-full rounded-xl border border-input bg-card pl-12 pr-4 text-base
                              placeholder:text-muted-foreground focus:border-primary">
            </div>

            <p class="text-xs text-muted-foreground">
                Must be at least 8 characters, with letters and numbers. Code expires 10 minutes after it was sent.
            </p>

            <button type="submit"
                    class="flex h-14 w-full items-center justify-center gap-3 rounded-xl bg-[#166534]
                           text-base font-bold text-white transition active:scale-[0.99]
                           dark:bg-primary dark:text-primary-foreground">
                VERIFY &amp; RESET PASSWORD
            </button>
        </form>

        <form method="POST" action="{{ route('password.otp.send') }}" class="mt-4">
            @csrf
            <input type="hidden" name="phone_number" value="{{ $phone }}">
            <button type="submit" class="w-full text-center text-sm font-semibold text-[#166534] dark:text-primary">
                Didn't get a code? Resend
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('password.request') }}" class="font-semibold text-[#166534] dark:text-primary">
                &larr; Back to Forgot Password
            </a>
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

            <h2 class="text-3xl font-bold text-[#0d3d1b] dark:text-foreground">Verify Code</h2>
            <p class="mt-1 text-sm text-muted-foreground">A 6-digit code was sent to {{ $masked }}.</p>

            @if ($errors->any())
                <x-ui.alert variant="destructive" class="mt-5">{{ $errors->first() }}</x-ui.alert>
            @endif
            @if (session('status'))
                <x-ui.alert variant="success" class="mt-5">{{ session('status') }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('password.otp.update') }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="phone_number" value="{{ old('phone_number', $phone) }}">

                <div class="space-y-1.5">
                    <label for="code-desktop" class="block text-sm font-medium">6-digit code</label>
                    <input id="code-desktop" type="text" name="code" value="{{ old('code') }}" required
                           inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                           class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-center
                                  text-lg font-bold tracking-[0.4em] focus:border-primary">
                </div>

                <div class="space-y-1.5">
                    <label for="password-otp-desktop" class="block text-sm font-medium">New password</label>
                    <input id="password-otp-desktop" type="password" name="password" required
                           autocomplete="new-password"
                           class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-sm
                                  focus:border-primary">
                </div>

                <div class="space-y-1.5">
                    <label for="password-otp-confirmation-desktop" class="block text-sm font-medium">Confirm new password</label>
                    <input id="password-otp-confirmation-desktop" type="password" name="password_confirmation" required
                           autocomplete="new-password"
                           class="h-11 w-full rounded-lg border border-input bg-card px-3.5 text-sm
                                  focus:border-primary">
                </div>

                <p class="text-xs text-muted-foreground">
                    Must be at least 8 characters, with letters and numbers. Code expires 10 minutes after it was sent.
                </p>

                <button type="submit"
                        class="h-11 w-full rounded-lg bg-[#166534] text-sm font-semibold text-white
                               transition hover:brightness-110 dark:bg-primary dark:text-primary-foreground">
                    Verify &amp; Reset Password
                </button>
            </form>

            <form method="POST" action="{{ route('password.otp.send') }}" class="mt-3">
                @csrf
                <input type="hidden" name="phone_number" value="{{ $phone }}">
                <button type="submit" class="w-full text-center text-sm font-semibold text-[#166534] dark:text-primary">
                    Didn't get a code? Resend
                </button>
            </form>

            <p class="mt-6 text-center text-sm">
                <a href="{{ route('password.request') }}" class="font-semibold text-[#166534] dark:text-primary">
                    &larr; Back to Forgot Password
                </a>
            </p>
        </div>
    </div>
</div>
@endsection
