@extends('layouts.guest')
@section('title', 'Pending Verification')

@section('content')
<div class="rounded-2xl bg-white p-8 text-center shadow-sm">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
        <svg class="h-7 w-7 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </div>

    <h2 class="mb-2 text-lg font-bold text-slate-800">Account Pending Verification</h2>
    <p class="mb-6 text-sm text-slate-600">
        Your registration has been submitted and is awaiting review by the Municipal Agriculture Office.
        You will be able to access the system once your account has been approved.
    </p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Logout
        </button>
    </form>
</div>
@endsection
