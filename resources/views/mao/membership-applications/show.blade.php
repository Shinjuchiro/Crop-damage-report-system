@extends('layouts.app')
@section('title', 'Review Application')
@section('heading', 'Review Farmer Registration')
@section('subheading', 'Verify the information before approving or rejecting')

@section('content')
<div x-data="{ confirmAction: null }" class="space-y-6">

    {{-- Personal information --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Personal Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted-foreground">Full Name</dt><dd class="font-bold text-foreground">{{ $farmer->full_name }}</dd></div>
            <div><dt class="text-muted-foreground">Date of Birth</dt><dd class="font-medium text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Sex</dt><dd class="font-medium capitalize text-foreground">{{ $farmer->sex }}</dd></div>
            <div><dt class="text-muted-foreground">Contact Number</dt><dd class="font-medium text-foreground">{{ $farmer->user->phone_number }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-muted-foreground">Address</dt><dd class="font-medium text-foreground">{{ $farmer->address }}</dd></div>
            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $farmer->barangay->name ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $farmer->association->name ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Account --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Account Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $farmer->user->username }}</dd></div>
            <div><dt class="text-muted-foreground">Email</dt><dd class="font-medium text-foreground">{{ $farmer->user->email }}</dd></div>
        </dl>
        {{-- Password is never displayed --}}
    </div>

    {{-- Farm ownership --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Ownership</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Farmer Type</dt>
                <dd class="font-medium text-foreground">{{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</dd>
            </div>

            @if ($farmer->ownership_type === 'tenant')
                <div><dt class="text-muted-foreground">Land Owner Name</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_name ?? '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Land Owner Contact</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_contact ?? '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Land Owner Location</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_location ?? '—' }}</dd></div>
            @else
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Barangay Certificate</dt>
                    <dd>
                        @if ($farmer->barangay_certificate_path)
                            <a href="{{ $farmer->barangay_certificate_url }}" target="_blank"
                               class="font-medium text-green-800 hover:underline">View Barangay Certificate</a>
                        @else
                            <span class="text-muted-foreground">Not provided</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Farm information --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Farm Size</dt>
                <dd class="font-medium text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Main Crops</dt>
                <dd class="font-medium text-foreground">
                    @forelse ($farmer->mainCrops as $mc)
                        {{ $mc->crop->name }}{{ $mc->crop_specify ? " ({$mc->crop_specify})" : '' }}@if(!$loop->last), @endif
                    @empty — @endforelse
                </dd>
            </div>
        </dl>
    </div>

    {{-- ---------- ASSOCIATION VERIFICATION ----------
         What the office is being asked to rely on when it decides: who
         vouched for this applicant, when, and what they said about it. The
         revision requires this page to show the association's verification
         status and remarks, and the reason is the whole point of the
         association-first workflow: the association knows whether this person
         is one of its members, and the office does not.

         Nothing recorded means the application was forwarded before October
         2026. It says so rather than showing a blank field, which would read
         as "the officer wrote nothing". --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Association Verification</h3>

        @if ($farmer->forwarded_at)
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Verified by</dt>
                    <dd class="font-medium text-foreground">
                        {{ $farmer->forwardedBy?->display_name ?? 'Not recorded' }}
                        <span class="block text-xs font-normal text-muted-foreground">
                            {{ $farmer->association->name ?? 'Association' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Forwarded to this office</dt>
                    <dd class="font-medium text-foreground">{{ $farmer->forwarded_at->format('M d, Y g:i A') }}</dd>
                </div>
                @if ($farmer->associationReviewer)
                    <div>
                        <dt class="text-muted-foreground">Review started by</dt>
                        <dd class="font-medium text-foreground">
                            {{ $farmer->associationReviewer->display_name }}
                            @if ($farmer->association_review_started_at)
                                <span class="block text-xs font-normal text-muted-foreground">
                                    {{ $farmer->association_review_started_at->format('M d, Y g:i A') }}
                                </span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($farmer->resubmission_count > 0)
                    <div>
                        <dt class="text-muted-foreground">Corrected and resubmitted</dt>
                        <dd class="font-medium text-foreground">
                            {{ $farmer->resubmission_count }}
                            {{ \Illuminate\Support\Str::plural('time', $farmer->resubmission_count) }}
                        </dd>
                    </div>
                @endif
            </dl>

            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-950/50">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">
                    Association remarks
                </p>
                <p class="mt-1 text-sm leading-relaxed text-green-900 dark:text-green-200">
                    {{ $farmer->forward_note ?: 'No remarks given.' }}
                </p>
            </div>
        @else
            <p class="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                Not yet verified by
                <span class="font-medium text-foreground">{{ $farmer->association->name ?? 'an association' }}</span>.
                This office cannot approve or reject the application until the association has verified
                and forwarded it.
            </p>
        @endif
    </div>

    {{-- ---------- THE OFFICE'S DECISION ----------
         Only once there is one. The rejection reason is required, so a
         rejected application always has one from October 2026 onward; an
         older one may not, and the field says Not recorded rather than
         inventing something. --}}
    @if ($farmer->decided_at || $farmer->decision_note)
        @php $appStatus = \App\Support\MembershipApplication::forFarmer($farmer); @endphp

        <div class="rounded-xl border border-border bg-card p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Office Decision</h3>

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Outcome</dt>
                    <dd class="mt-0.5"><x-ui.status :value="$appStatus" /></dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Decided by</dt>
                    <dd class="font-medium text-foreground">
                        {{ $farmer->decidedBy?->display_name ?? 'Not recorded' }}
                        @if ($farmer->decided_at)
                            <span class="block text-xs font-normal text-muted-foreground">
                                {{ $farmer->decided_at->format('M d, Y g:i A') }}
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($farmer->decision_note)
                <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ $appStatus === \App\Support\MembershipApplication::REJECTED ? 'Reason for rejection' : 'Note' }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-foreground">{{ $farmer->decision_note }}</p>
                </div>
            @endif
        </div>
    @endif

    {{-- ---------- APPLICATION HISTORY ----------
         Every recorded move, newest first: the action, which status it moved
         from and to, who did it, what they said, and when (revision section
         10). Read from the existing audit log rather than a second history
         table, which the revision asks for explicitly.

         A table on desktop and stacked cards on a phone, matching how the
         rest of this system handles a list that has more than two columns'
         worth of information. --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Application History</h3>

        @if ($history->isEmpty())
            <p class="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                No recorded moves. Applications handled before October 2026 were not tracked step by
                step; anything that happens from now on appears here.
            </p>
        @else
            {{-- Phone: one card per entry. --}}
            <ul class="space-y-3 lg:hidden">
                @foreach ($history as $entry)
                    <li class="rounded-lg bg-muted px-3 py-2.5">
                        <p class="text-sm font-medium text-foreground">{{ $entry->action }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ \App\Support\MembershipApplication::transitionLabel($entry->from_status) }}
                            <span aria-hidden="true">&rarr;</span><span class="sr-only">to</span>
                            <span class="font-medium text-foreground">
                                {{ \App\Support\MembershipApplication::transitionLabel($entry->to_status) }}
                            </span>
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $entry->user?->display_name ?? 'Unknown user' }}
                            &middot; {{ $entry->created_at?->format('M d, Y g:i A') ?? '—' }}
                        </p>
                        @if ($entry->remarks)
                            <p class="mt-1.5 border-t border-border pt-1.5 text-xs text-foreground">{{ $entry->remarks }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Desktop: four columns. Remarks share the Action cell rather
                 than taking a fifth, because a note runs to a thousand
                 characters and a column that wide squeezes the other four. --}}
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                            <th class="px-3 py-3">Action</th>
                            <th class="px-3 py-3">From</th>
                            <th class="px-3 py-3">To</th>
                            <th class="px-3 py-3">By</th>
                            <th class="px-3 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($history as $entry)
                            <tr class="hover:bg-muted/60">
                                <td class="px-3 py-3">
                                    <span class="font-medium text-foreground">{{ $entry->action }}</span>
                                    @if ($entry->remarks)
                                        <span class="mt-1 block text-xs text-muted-foreground">{{ $entry->remarks }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ \App\Support\MembershipApplication::transitionLabel($entry->from_status) }}
                                </td>
                                <td class="px-3 py-3 font-medium text-foreground">
                                    {{ \App\Support\MembershipApplication::transitionLabel($entry->to_status) }}
                                </td>
                                <td class="px-3 py-3 text-foreground">{{ $entry->user?->display_name ?? 'Unknown' }}</td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ $entry->created_at?->format('M d, Y g:i A') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Actions

         Gated on the stage, not on the account status (revision step 6).
         'pending' used to mean "nobody has decided this yet", which was the
         same as "the office may decide it now" because every application
         came straight here. It is not the same any more: an application
         still with a farmers' association, or sitting with the farmer for
         correction, is also pending and is not the office's to decide.

         Return for Correction is on the list page's panel rather than
         repeated here. This full page exists for reading a registration at
         length; the panel is where the office works through the queue, and
         a third copy of the same form is a third place to keep in step. --}}
    @if (\App\Support\MembershipApplication::isOfficesTurn($farmer))
        <div class="rounded-xl border border-border bg-card p-6">
            <h3 class="mb-1 text-sm font-semibold text-foreground">Review Decision</h3>
            <p class="mb-4 text-sm text-muted-foreground">Please confirm you have reviewed all information above.</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button @click="confirmAction = 'approve'"
                        class="rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                    Approve Registration
                </button>
                <button @click="confirmAction = 'reject'"
                        class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Reject Registration
                </button>
                <a href="{{ route('mao.membership-applications.index') }}"
                   class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                    Back to List
                </a>
            </div>
        </div>

        {{-- Approve confirmation --}}
        <div x-show="confirmAction === 'approve'" x-cloak
             class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="my-auto w-full max-w-md rounded-xl bg-card p-6">
                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Approval</h3>
                <p class="mb-5 text-sm text-muted-foreground">
                    Are you sure you want to approve <strong>{{ $farmer->full_name }}</strong>?
                    The farmer will gain full access to the system.
                </p>
                <div class="flex gap-3">
                    <button @click="confirmAction = null"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('mao.membership-applications.approve', $farmer) }}" class="flex-1">
                        @csrf @method('PUT')
                        <button class="w-full rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                            Confirm Approval
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reject confirmation --}}
        <div x-show="confirmAction === 'reject'" x-cloak
             class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="my-auto w-full max-w-md rounded-xl bg-card p-6">
                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Rejection</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Are you sure you want to reject <strong>{{ $farmer->full_name }}</strong>'s
                    registration? This ends the application. Their membership will not be activated,
                    and they cannot correct and resubmit it. If the registration is only incomplete,
                    return it for correction from the Membership Applications list instead.
                </p>
                <form method="POST" action="{{ route('mao.membership-applications.reject', $farmer) }}">
                    @csrf @method('PUT')
                    {{-- Required, matching the controller's min:10. The
                         farmer reads this on their own screen word for word,
                         and it is the only thing that tells them whether to
                         try again. --}}
                    <label for="show_rejection_reason" class="mb-1 block text-sm font-medium text-foreground">
                        Reason for rejection <span class="text-red-600">*</span>
                    </label>
                    <textarea id="show_rejection_reason" name="rejection_reason" rows="3"
                              required minlength="10" maxlength="1000"
                              placeholder="For example: the applicant is not a farmer in Tanza and the address could not be verified."
                              class="mb-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">{{ old('rejection_reason') }}</textarea>
                    <p class="mb-4 text-xs text-muted-foreground">
                        At least 10 characters. The farmer is shown this word for word.
                    </p>
                    <div class="flex gap-3">
                        <button type="button" @click="confirmAction = null"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        @php $appStatus = \App\Support\MembershipApplication::forFarmer($farmer); @endphp
        <div class="rounded-xl border border-border bg-card p-6">
            <div class="mb-2"><x-ui.status :value="$appStatus" /></div>
            <p class="text-sm text-muted-foreground">
                {{ \App\Support\MembershipApplication::means($appStatus) ?: 'This application has already been decided.' }}
            </p>
            <a href="{{ route('mao.membership-applications.index') }}"
               class="mt-3 inline-block rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                Back to List
            </a>
        </div>
    @endif
</div>
@endsection
