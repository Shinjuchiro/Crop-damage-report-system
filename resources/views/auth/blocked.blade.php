@extends('layouts.guest')
@section('title', 'Account Unavailable')

{{--
    THE ONE SCREEN A REJECTED FARMER EVER SEES (rewritten Oct 2026)

    A rejected account can still sign in: EnsureActiveAccount authenticates
    them and redirects here. Everything else in the farmer area, the
    notification bell included, sits behind the 'active' middleware they
    cannot pass.

    That mattered, because this page used to say only "this account is
    currently inactive or was not approved, please contact the Municipal
    Agriculture Office". The office had written a rejection reason, the
    system had sent it as an in-app notification, and the one person it was
    addressed to had no way to open it. The revision requires that a farmer
    can read why their application was rejected, so it is shown here.

    Two different situations land on this page and they are not the same
    thing, so they no longer share one message:

      REJECTED   a decision about this person's application. They are owed
                 the reason, and they get it word for word.
      INACTIVE   the office archived the record, or deactivated a technician
                 or officer account. That is internal filing, not a verdict,
                 so there is nothing to explain and the generic message is
                 the honest one.

    $farmer is null for a technician or officer whose account was
    deactivated; they reach this page too and get the generic message.
--}}

@section('content')
<div class="rounded-2xl bg-white p-8 shadow-sm">

    @if ($reason)
        {{-- ---------- REJECTED, WITH A REASON ---------- --}}
        <div class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                <svg class="h-7 w-7 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h2 class="mb-2 text-lg font-bold text-slate-800">Registration Not Approved</h2>
            <p class="text-sm text-slate-600">
                The Municipal Agriculture Office reviewed your registration and did not approve it
                @if ($decidedAt)
                    on {{ $decidedAt->format('F j, Y') }}.
                @else
                    .
                @endif
            </p>
        </div>

        {{-- The office's own words, not a paraphrase. Left-aligned and in a
             panel of its own because it is the thing the farmer came here to
             read, and centred body text is hard to read past a line or two. --}}
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-left">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">
                Reason given by the office
            </p>
            <p class="mt-1 text-sm leading-relaxed text-red-900">{{ $reason }}</p>
        </div>

        <p class="mt-5 text-left text-sm text-slate-600">
            If you believe this was a mistake, or you can provide what was missing, please contact the
            Municipal Agriculture Office. They can review your registration again.
        </p>
    @else
        {{-- ---------- ARCHIVED, OR A NON-FARMER ACCOUNT ---------- --}}
        <div class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                <svg class="h-7 w-7 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h2 class="mb-2 text-lg font-bold text-slate-800">Account Not Available</h2>
            <p class="text-sm text-slate-600">
                @if ($status === \App\Support\MembershipApplication::REJECTED)
                    {{-- Rejected, but decided before Oct 2026, so no reason was
                         stored. Saying the office holds it is better than
                         implying none was given. --}}
                    Your registration was not approved. The Municipal Agriculture Office can tell you
                    why and whether you can apply again.
                @else
                    This account is currently inactive.
                    Please contact the Municipal Agriculture Office for assistance.
                @endif
            </p>
        </div>
    @endif

    <div class="mt-6 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Logout
            </button>
        </form>
    </div>
</div>
@endsection
