@extends('layouts.app')

@section('title', 'Membership Applications')
@section('hideHeading', true)

@php
    /* The badge map that used to live here is gone (Oct 2026). It coloured
       four account statuses with Tailwind classes written out in this file,
       which is the thing x-ui.status exists to prevent: that component's own
       opening comment says new statuses belong in its map so a status cannot
       be green on one screen and grey on another, and this page was the one
       screen going its own way.

       It also could not show the new stages at all. An application now
       travels through the farmers' association before it reaches the office
       (revision step 6), so "Pending" is four different situations: nobody
       has opened it, an officer is reading it, it has been forwarded, or the
       farmer has been asked to fix something. App\Support\MembershipApplication
       works out which, and x-ui.status colours it. */
    use App\Support\MembershipApplication;

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div x-data="{ archiving: null, confirmAction: null }">
    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            <x-ui.card title="Membership Applications">

                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    {{-- "All Status" is an option with a value, not the
                         select's empty placeholder. An empty option submits
                         status= with nothing after it, which
                         ConvertEmptyStringsToNull turns into null, which the
                         controller's default then reads as Pending - so
                         choosing All Status used to come back filtered to
                         Pending with the dropdown reset. --}}
                    <x-ui.select name="status" onchange="this.form.submit()"
                                 :options="$filters + ['all' => 'All Status']"
                                 :selected="$status" class="sm:w-56" />

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-48" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search applicant"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'barangay_id']) || $status !== 'awaiting_mao')
                        <a href="{{ route('mao.membership-applications.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per application ----------
                     Archive stays on the table and in the detail panel: it is
                     a deliberate action and does not belong on a card whose
                     whole surface is a link. --}}
                <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($applications as $application)
                        <li>
                            <x-ui.record-card :href="$viewUrl($application->id)"
                                              :eyebrow="'APP-' . str_pad($application->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$application->full_name"
                                              :status="MembershipApplication::forFarmer($application)"
                                              action="Review application" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No applications match these filters</p>
                        </li>
                    @endforelse
                </ul>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                {{-- FOUR COLUMNS (revision section 3.1)

                                     Date Applied and Barangay used to sit
                                     between the name and the status. Both
                                     are in View, on a registration that
                                     also carries the applicant's address,
                                     contact number, farm and ownership
                                     papers - which is exactly why this list
                                     should show as little as it can get
                                     away with (section 3.4). --}}
                                <th class="px-3 py-3">Applicant ID</th>
                                <th class="px-3 py-3">Applicant Name</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($applications as $application)
                                @php $rowStatus = MembershipApplication::forFarmer($application); @endphp
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $application->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">
                                        APP-{{ str_pad($application->id, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="px-3 py-4 font-bold text-foreground">{{ $application->full_name }}</td>
                                    <td class="px-3 py-4 text-center">
                                        <x-ui.status :value="$rowStatus" />
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($application->id)" variant="view" size="sm">View</x-ui.button>

                                            @if ($rowStatus !== MembershipApplication::ARCHIVED)
                                                <button type="button"
                                                        @click="archiving = { id: {{ $application->id }}, name: @js($application->full_name) }"
                                                        class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                                    Archive
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">No applications match these filters</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            New farmer registrations appear here for review.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-border pt-5">{{ $applications->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an application from the list to review it.">
            @if ($selected)
                @php
                    $farmer    = $selected;
                    $appStatus = MembershipApplication::forFarmer($farmer);
                    $ourTurn   = MembershipApplication::isOfficesTurn($farmer);
                @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        APP-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $farmer->full_name }}</h3>
                    <div class="mt-2"><x-ui.status :value="$appStatus" /></div>
                    <p class="mt-2 text-xs text-muted-foreground">{{ MembershipApplication::means($appStatus) }}</p>

                </div>

                {{-- Our own remarks, while the application is back with the
                     association. The office can see what it asked for and
                     that nothing else is expected of it yet. --}}
                @if ($appStatus === MembershipApplication::RETURNED_TO_ASSOCIATION && $farmer->mao_return_note)
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm dark:border-amber-800 dark:bg-amber-950/50">
                        <p class="font-semibold text-amber-900 dark:text-amber-200">
                            Returned to the association by this office
                            @if ($farmer->mao_returned_at)
                                on {{ $farmer->mao_returned_at->format('M d, Y') }}
                            @endif
                            @if ($farmer->maoReturnedBy)
                                ({{ $farmer->maoReturnedBy->display_name }})
                            @endif
                        </p>
                        <p class="mt-1 text-amber-900 dark:text-amber-100">{{ $farmer->mao_return_note }}</p>
                    </div>
                @endif

                {{-- The last correction note, from either side. The office
                     needs to see its own as much as the association's: an
                     application on its third pass with the same note each
                     time is a phone call, not a fourth note. --}}
                @if ($farmer->return_note)
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm dark:border-red-900 dark:bg-red-950/50">
                        <p class="font-semibold text-red-800 dark:text-red-300">
                            Last returned for correction
                            {{ $farmer->returned_by_role === 'mao' ? 'by this office' : 'by the association' }}
                            @if ($farmer->returned_at)
                                on {{ $farmer->returned_at->format('M d, Y') }}
                            @endif
                        </p>
                        <p class="mt-1 text-red-700 dark:text-red-200">{{ $farmer->return_note }}</p>
                        @if ($farmer->resubmission_count > 0)
                            <p class="mt-1.5 text-xs text-red-700 dark:text-red-300">
                                Resubmitted {{ $farmer->resubmission_count }}
                                {{ \Illuminate\Support\Str::plural('time', $farmer->resubmission_count) }} since then.
                            </p>
                        @endif
                    </div>
                @endif

                <div class="space-y-4 border-t border-border pt-4 text-sm">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Personal Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Date of Birth</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Sex</dt><dd class="min-w-0 text-right font-medium capitalize text-foreground">{{ $farmer->sex }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Contact</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->user->phone_number }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Address</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->address }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Barangay</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->barangay->name ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Association</dt><dd class="min-w-0 text-right font-bold text-foreground">{{ $farmer->association->name ?? '-' }}</dd></div>
                        </dl>
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Account Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Username</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->user->username }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Email</dt><dd class="min-w-0 break-all text-right font-medium text-foreground">{{ $farmer->user->email }}</dd></div>
                        </dl>
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Ownership</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmer Type</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</dd></div>
                            @if ($farmer->ownership_type === 'tenant')
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Land Owner</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->landowner_name ?? '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Owner Contact</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->landowner_contact ?? '-' }}</dd></div>
                            @else
                                <div class="flex justify-between gap-3">
                                    <dt class="shrink-0 text-muted-foreground">Barangay Certificate</dt>
                                    <dd class="min-w-0 text-right font-medium text-foreground">
                                        @if ($farmer->barangay_certificate_path)
                                            <a href="{{ $farmer->barangay_certificate_url }}" target="_blank" class="text-green-800 hover:underline">View</a>
                                        @else
                                            Not provided
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                        <x-farmer.supporting-documents :farmer="$farmer" class="mt-3" />
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farm Size</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '-' }}</dd></div>
                            <div class="flex justify-between gap-3">
                                <dt class="shrink-0 text-muted-foreground">Main Crops</dt>
                                <dd class="min-w-0 text-right font-medium text-foreground">
                                    @forelse ($farmer->mainCrops as $mc)
                                        {{ $mc->crop->name }}{{ $mc->crop_specify ? " ({$mc->crop_specify})" : '' }}@if(!$loop->last), @endif
                                    @empty - @endforelse
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- ---------- ASSOCIATION VERIFICATION ----------
                         What the office is being asked to rely on. The
                         revision requires this panel to show the association's
                         verification status and its remarks, because the whole
                         reason the application came through an association is
                         that the association knows something the office does
                         not: whether this person is really one of their
                         members.

                         Shown in green when it has been verified, because the
                         office reads this to decide whether it may act, and
                         the answer should be visible without reading a date.

                         An application forwarded before October 2026 has no
                         officer and no remarks recorded. It says so plainly
                         rather than showing a blank, which would read as "the
                         officer wrote nothing" instead of "this was not
                         tracked yet". --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Association Verification
                        </p>

                        @if ($farmer->forwarded_at)
                            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2.5 dark:border-green-900 dark:bg-green-950/50">
                                <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                                    Verified by {{ $farmer->association->name ?? 'the association' }}
                                </p>
                                <dl class="mt-1.5 space-y-1 text-xs">
                                    <div class="flex justify-between gap-3">
                                        <dt class="shrink-0 text-green-700 dark:text-green-400">Verified by</dt>
                                        <dd class="min-w-0 text-right font-medium text-green-900 dark:text-green-200">
                                            {{ $farmer->forwardedBy?->display_name ?? 'Not recorded' }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="shrink-0 text-green-700 dark:text-green-400">Forwarded</dt>
                                        <dd class="min-w-0 text-right font-medium text-green-900 dark:text-green-200">
                                            {{ $farmer->forwarded_at->format('M d, Y g:i A') }}
                                        </dd>
                                    </div>
                                    @if ($farmer->associationReviewer)
                                        <div class="flex justify-between gap-3">
                                            <dt class="shrink-0 text-green-700 dark:text-green-400">Review started by</dt>
                                            <dd class="min-w-0 text-right font-medium text-green-900 dark:text-green-200">
                                                {{ $farmer->associationReviewer->display_name }}
                                            </dd>
                                        </div>
                                    @endif
                                </dl>

                                <div class="mt-2 border-t border-green-200 pt-2 dark:border-green-900">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">
                                        Association remarks
                                    </p>
                                    <p class="mt-0.5 text-sm text-green-900 dark:text-green-200">
                                        {{ $farmer->forward_note ?: 'No remarks given.' }}
                                    </p>
                                </div>
                            </div>
                        @else
                            {{-- Not verified. This is the state the office
                                 cannot act from, and saying why is more use
                                 than an empty panel. --}}
                            <div class="rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                                Not yet verified by
                                <span class="font-medium text-foreground">{{ $farmer->association->name ?? 'an association' }}</span>.
                                This office cannot approve or reject it until the association has verified
                                and forwarded it.
                            </div>
                        @endif
                    </div>

                    {{-- ---------- THE OFFICE'S OWN DECISION ----------
                         Only once there is one. Approving records who and
                         when; rejecting records the reason as well, which is
                         required (see the controller). An application decided
                         before October 2026 has no decided_by recorded, and
                         the migration deliberately did not invent one. --}}
                    @if ($farmer->decided_at || $farmer->decision_note)
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                Office Decision
                            </p>
                            <dl class="space-y-1.5">
                                <div class="flex justify-between gap-3">
                                    <dt class="shrink-0 text-muted-foreground">Outcome</dt>
                                    <dd class="min-w-0 text-right"><x-ui.status :value="$appStatus" /></dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="shrink-0 text-muted-foreground">Decided by</dt>
                                    <dd class="min-w-0 text-right font-medium text-foreground">
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
                                <div class="mt-2 rounded-lg bg-muted px-3 py-2.5">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ $appStatus === MembershipApplication::REJECTED ? 'Reason for rejection' : 'Note' }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-foreground">{{ $farmer->decision_note }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- ---------- APPLICATION HISTORY ----------
                         Every recorded move, newest first (revision section
                         10). Read from the existing audit log by
                         App\Support\MembershipApplication::history(), not from
                         a second history table, because the revision says to
                         reuse the one that exists and one does.

                         Entries from before October 2026 have no transition
                         recorded, so an application decided before then shows
                         what it has done since and nothing earlier. The empty
                         state says that, instead of implying nothing ever
                         happened. --}}
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Application History
                        </p>

                        @forelse ($history as $entry)
                            <div class="mb-2 rounded-lg bg-muted px-3 py-2">
                                <p class="text-sm font-medium text-foreground">{{ $entry->action }}</p>

                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ MembershipApplication::transitionLabel($entry->from_status) }}
                                    <span aria-hidden="true">&rarr;</span>
                                    <span class="sr-only">to</span>
                                    <span class="font-medium text-foreground">
                                        {{ MembershipApplication::transitionLabel($entry->to_status) }}
                                    </span>
                                </p>

                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ $entry->user?->display_name ?? 'Unknown user' }}
                                    &middot; {{ $entry->created_at?->format('M d, Y g:i A') ?? '-' }}
                                </p>

                                @if ($entry->remarks)
                                    <p class="mt-1.5 border-t border-border pt-1.5 text-xs text-foreground">
                                        {{ $entry->remarks }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                                No recorded moves. Applications handled before October 2026 were not
                                tracked step by step; anything that happens from now on appears here.
                            </p>
                        @endforelse
                    </div>
                </div>

                {{-- ---------- THE OFFICE'S DECISION ----------
                     Only on an application a farmers' association has
                     forwarded (revision step 6). The office can read every
                     application from the moment it is submitted, so this
                     panel opens for all of them; the buttons are what
                     waits. The controller checks the same thing, because
                     hiding a button is not a rule. --}}
                @if ($ourTurn)
                    <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4">
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button @click="confirmAction = 'approve'"
                                    class="w-full rounded-lg bg-green-800 px-3 py-2 text-sm font-semibold text-white hover:bg-green-900">
                                Approve
                            </button>
                            <button @click="confirmAction = 'reject'"
                                    class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                Reject
                            </button>
                        </div>

                        {{-- The middle answer, and the one the office had no
                             way to give before: something is wrong with the
                             registration but the applicant is not somebody
                             to turn away. --}}
                        <button @click="confirmAction = 'return'"
                                class="w-full rounded-lg border border-input px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted/60">
                            Return to Association
                        </button>
                    </div>

                    {{-- Approve confirmation --}}
                    <div x-show="confirmAction === 'approve'" x-cloak
                         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6">
                            <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Approval</h3>
                            <p class="mb-3 text-sm text-muted-foreground">
                                Are you sure you want to approve <strong>{{ $farmer->full_name }}</strong>?
                                The farmer will gain full access to the system and become an active member of
                                <strong>{{ $farmer->association->name ?? 'their association' }}</strong>.
                            </p>

                            {{-- Repeating the association's verification here,
                                 in the dialog, because this is the moment the
                                 office is relying on it and the panel behind
                                 the dialog is no longer readable. --}}
                            <p class="mb-4 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                                <span class="font-semibold text-foreground">Verified by the association</span>
                                @if ($farmer->forwarded_at)
                                    on {{ $farmer->forwarded_at->format('M d, Y') }}@if ($farmer->forwardedBy), {{ $farmer->forwardedBy->display_name }}@endif.
                                @endif
                                {{ $farmer->forward_note ? 'Remarks: ' . $farmer->forward_note : 'No remarks given.' }}
                            </p>

                            <form method="POST" action="{{ route('mao.membership-applications.approve', $farmer) }}">
                                @csrf @method('PUT')

                                {{-- Optional. Approving is the expected
                                     outcome and usually needs no explanation;
                                     a required field here would collect a
                                     column of full stops. The rejection
                                     reason, which ends an application, is
                                     required. --}}
                                <label for="approval_note" class="mb-1 block text-sm font-medium text-foreground">
                                    Note <span class="font-normal text-muted-foreground">(optional)</span>
                                </label>
                                <textarea id="approval_note" name="approval_note" rows="2" maxlength="1000"
                                          placeholder="Anything worth recording with this approval."
                                          class="mb-4 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">{{ old('approval_note') }}</textarea>

                                <div class="flex gap-3">
                                    <button type="button" @click="confirmAction = null"
                                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="flex-1 rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                                        Confirm Approval
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Reject confirmation --}}
                    <div x-show="confirmAction === 'reject'" x-cloak
                         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6">
                            <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Rejection</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Are you sure you want to reject <strong>{{ $farmer->full_name }}</strong>'s
                                registration? This ends the application. Their membership will not be
                                activated, and they cannot correct and resubmit it. If the registration is
                                only incomplete, use <strong>Return to Association</strong> instead.
                            </p>
                            <form method="POST" action="{{ route('mao.membership-applications.reject', $farmer) }}">
                                @csrf @method('PUT')

                                {{-- REQUIRED, where it used to be optional.
                                     A farmer told only "not approved" has
                                     nothing to act on and no way to know
                                     whether to try again, and the reason is
                                     now stored on the application rather than
                                     living only in a notification body.
                                     minlength matches the controller's
                                     min:10, because "no" passes 'required'
                                     and explains nothing. --}}
                                <label for="rejection_reason" class="mb-1 block text-sm font-medium text-foreground">
                                    Reason for rejection <span class="text-red-600">*</span>
                                </label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="3"
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

                    {{-- Return for Correction --}}
                    <div x-show="confirmAction === 'return'" x-cloak
                         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6">
                            <h3 class="mb-2 text-lg font-semibold text-foreground">Return this application to the association?</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                It goes back to
                                <strong>{{ $farmer->association->name ?? 'the farmers\' association' }}</strong>
                                with your remarks. The association then returns it to
                                {{ $farmer->full_name }} to correct, and it comes back through the association
                                before it reaches this office again. Nothing the farmer entered is lost and the
                                account stays pending.
                            </p>
                            <form method="POST" action="{{ route('mao.membership-applications.return', $farmer) }}">
                                @csrf @method('PUT')
                                <label for="mao_return_note" class="mb-1 block text-sm font-medium text-foreground">
                                    Reason for returning <span class="text-red-600">*</span>
                                </label>
                                <textarea id="mao_return_note" name="return_note" rows="4" required minlength="10" maxlength="1000"
                                          placeholder="For example: the barangay certificate is unreadable, please ask the farmer to upload a clearer photo."
                                          class="mb-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">{{ old('return_note') }}</textarea>
                                <p class="mb-4 text-xs text-muted-foreground">
                                    At least 10 characters. The association reads this and passes it on to the farmer.
                                </p>
                                <div class="flex gap-3">
                                    <button type="button" @click="confirmAction = null"
                                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                                        Confirm &amp; Return to Association
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @elseif (in_array($appStatus, [MembershipApplication::SUBMITTED, MembershipApplication::UNDER_REVIEW], true))
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        Still with <strong>{{ $farmer->association->name ?? 'the farmers\' association' }}</strong>.
                        You can read it, but it has to be forwarded before this office can approve, reject
                        or return it.
                    </p>
                @elseif ($appStatus === MembershipApplication::RETURNED_TO_ASSOCIATION)
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        Returned to <strong>{{ $farmer->association->name ?? 'the farmers\' association' }}</strong>.
                        They pass your remarks on to the farmer, and the application reaches this office
                        again once the farmer has corrected it and the association has forwarded it.
                    </p>
                @elseif ($appStatus === MembershipApplication::RETURNED)
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        Waiting on the farmer to correct it. It goes back to their association first, and
                        reaches this office again once they have forwarded it.
                    </p>
                @else
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        This application has already been decided.
                    </p>
                @endif
            @endif
        </x-ui.detail-panel>
    </div>

    {{-- Archive confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this application?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong> will be taken out of the review queue.
                Nothing is deleted, and the record stays available under the Archived filter.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/membership-applications') }}/${archiving?.id}/archive`" class="flex-1">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Confirm Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
