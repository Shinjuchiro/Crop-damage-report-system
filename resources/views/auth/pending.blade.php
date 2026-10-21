@extends('layouts.guest')
@section('title', 'Application Status')
@php($hideBrandHeader = true)

@php
    use App\Support\MembershipApplication;

    /* One screen, four answers (revision step 6).

       This page used to say the same sentence to everybody: your
       registration is awaiting review by the Municipal Agriculture Office.
       An application now goes to the farmers' association first, so that
       sentence is wrong at three of the four stages, and at the fourth it is
       the opposite of the truth - the farmer has been asked to correct
       something and the system is waiting on them, not the other way round.

       $farmer is null for a user who has no farmer record at all: a
       technician or officer whose account the office has set to pending or
       inactive lands here too, from the same 'active' middleware. They get
       the generic message below rather than an error. */
    $returned = $status === MembershipApplication::RETURNED;

    [$tone, $icon] = $returned
        ? ['red', 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z']
        : ['amber', 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z'];
@endphp

@section('content')
{{-- The success message after a resubmission lands here, so this layout has
     to render it; layouts.guest has no alert slot of its own. --}}
@if (session('status'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
        {{ session('status') }}
    </div>
@endif

<div class="rounded-2xl bg-white p-8 shadow-sm">

    <div class="text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full
                    {{ $returned ? 'bg-red-100' : 'bg-amber-100' }}">
            <svg class="h-7 w-7 {{ $returned ? 'text-red-600' : 'text-amber-600' }}"
                 fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
            </svg>
        </div>

        <h2 class="mb-2 text-lg font-bold text-slate-800">
            @if (! $farmer)
                Account Pending Verification
            @elseif ($returned)
                Your Application Needs Correction
            @else
                {{ MembershipApplication::label($status) }}
            @endif
        </h2>

        <p class="text-sm text-slate-600">
            @if (! $farmer)
                Your account is awaiting review by the Municipal Agriculture Office. You will be able to
                use the system once it has been approved.
            @elseif ($returned)
                Something in your registration needs to be fixed before it can go any further. Once you
                have corrected it, it goes back to your Farmers' Association for review.
            @else
                {{ MembershipApplication::means($status) }}
            @endif
        </p>
    </div>

    {{-- WHAT WAS WRONG, WORD FOR WORD
         Not paraphrased and not shortened. Whoever returned it wrote this
         for the farmer to read, and a farmer who cannot tell what to change
         will travel to the office to ask. --}}
    @if ($returned && $farmer->return_note)
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-left">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-800">What needs fixing</p>
            <p class="mt-1 text-sm text-red-700">{{ $farmer->return_note }}</p>
            <p class="mt-2 text-xs text-red-700">
                Returned
                {{ $farmer->returned_by_role === 'mao'
                    ? 'by the Municipal Agriculture Office'
                    : 'by ' . ($farmer->association->name ?? 'your Farmers\' Association') }}
                @if ($farmer->returned_at)
                    on {{ $farmer->returned_at->format('M d, Y') }}
                @endif
            </p>
        </div>
    @endif

    {{-- WHERE IT IS, AS THREE STEPS
         A farmer waiting on an application wants to know who has it, not
         only that it is being handled. Hidden for a non-farmer account,
         which has no association stage to be at. --}}
    @if ($farmer && ! $returned)
        @php
            $atAssociation = in_array($status, [MembershipApplication::SUBMITTED, MembershipApplication::UNDER_REVIEW], true);
            $atOffice      = $status === MembershipApplication::FORWARDED;
            $done          = in_array($status, [MembershipApplication::APPROVED, MembershipApplication::REJECTED], true);

            $steps = [
                ['Your Farmers\' Association', $farmer->association->name ?? 'Reviewing your application', $atAssociation, $atOffice || $done],
                ['Municipal Agriculture Office', 'Verifies and decides', $atOffice, $done],
                ['Approved', 'You can start using the system', $status === MembershipApplication::APPROVED, false],
            ];
        @endphp

        <ol class="mt-6 space-y-3 text-left">
            @foreach ($steps as [$title, $detail, $current, $passed])
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold
                                 {{ $passed ? 'bg-green-700 text-white' : ($current ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-500') }}">
                        @if ($passed) &check; @else {{ $loop->iteration }} @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold {{ $current || $passed ? 'text-slate-800' : 'text-slate-500' }}">
                            {{ $title }}
                        </span>
                        <span class="block text-xs text-slate-500">{{ $detail }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif

    <div class="mt-6 flex flex-col gap-3">
        @if ($returned)
            <a href="{{ route('farmer.application.correct') }}"
               class="rounded-lg bg-green-800 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-green-900">
                Correct My Application
            </a>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Logout
            </button>
        </form>
    </div>
</div>
@endsection
