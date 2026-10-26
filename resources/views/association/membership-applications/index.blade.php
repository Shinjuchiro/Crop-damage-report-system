@extends('layouts.app')

@section('title', 'Membership Applications')
@section('heading', 'Membership Applications')
@section('heading-fil', 'Mga Aplikasyon sa Pagsapi')
@section('subheading', 'Confirm that each applicant is one of your members, then forward the application to the Municipal Agriculture Office.')

@php
    use App\Support\MembershipApplication;

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
{{-- acting holds whichever confirmation dialog is open: 'review', 'forward'
     or 'return'. One variable rather than three booleans, so two dialogs
     can never be open at once. --}}
<div x-data="{ acting: null }">

    {{-- WHAT THIS SCREEN IS, IN ONE LINE
         An officer arriving here for the first time has never seen this
         workflow; the office asked for it after the system was already in
         use. The two things they need to know are that they do not decide
         membership and that the office is waiting on them. --}}
    <div class="mb-5 rounded-xl border border-border bg-muted/40 px-4 py-3 text-sm">
        <p class="font-semibold text-foreground">
            @if ($awaitingUs === 0)
                No applications are waiting on your association.
            @else
                {{ $awaitingUs }} {{ \Illuminate\Support\Str::plural('application', $awaitingUs) }}
                {{ $awaitingUs === 1 ? 'is' : 'are' }} waiting on your association.
            @endif
        </p>
        <p class="mt-1 text-muted-foreground">
            Your job is to confirm the applicant really is one of your members. Forward the ones that
            are, and send back the ones with something missing. The Municipal Agriculture Office makes
            the final decision to approve or reject.
        </p>
    </div>

    <x-ui.card title="Applications">

        @if ($errors->any())
            <x-ui.alert variant="destructive" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        {{-- Filters. Every option carries a real value; see the note on
             MembershipApplicationController::FILTERS for what an empty one
             does to a defaulted getter. --}}
        <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <x-ui.select name="stage" onchange="this.form.submit()"
                         :options="$filters" :selected="$stage" class="sm:w-56" />

            <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                         :options="$barangays->pluck('name', 'id')"
                         :selected="request('barangay_id')" class="sm:w-48" />

            <x-ui.input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search applicant" class="sm:min-w-[14rem] sm:flex-1" />

            @if (request()->hasAny(['search', 'barangay_id']) || $stage !== 'open')
                <a href="{{ route('association.membership-applications.index') }}"
                   class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </form>

        {{-- ---------- PHONE: one card per application ---------- --}}
        <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
            @forelse ($applications as $application)
                <li>
                    <x-ui.record-card :href="$viewUrl($application->id)"
                                      :eyebrow="'APP-' . str_pad($application->id, 3, '0', STR_PAD_LEFT)"
                                      :title="$application->full_name"
                                      :status="MembershipApplication::forFarmer($application)"
                                      action="Open application" />
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                    <p class="text-sm font-medium text-muted-foreground">No applications match these filters</p>
                </li>
            @endforelse
        </ul>

        {{-- ---------- FOUR COLUMNS (revision section 3.1) ----------
             Everything else about an applicant is behind Open: a
             registration carries their address, contact number, birth date
             and ownership papers, which is exactly why a list of them
             should show as little as it can. --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                        <th class="px-3 py-3">Applicant ID</th>
                        <th class="px-3 py-3">Applicant Name</th>
                        <th class="px-3 py-3 text-center">Status</th>
                        <th class="px-3 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @forelse ($applications as $application)
                        <tr class="hover:bg-muted/60 {{ $selected?->id === $application->id ? 'bg-muted/60' : '' }}">
                            <td class="px-3 py-4 text-foreground">
                                APP-{{ str_pad($application->id, 3, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-3 py-4 font-bold text-foreground">{{ $application->full_name }}</td>
                            <td class="px-3 py-4 text-center">
                                <x-ui.status :value="MembershipApplication::forFarmer($application)" />
                            </td>
                            <td class="px-3 py-4 text-center">
                                <x-ui.button :href="$viewUrl($application->id)" variant="view" size="sm">Open</x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-14 text-center">
                                <p class="text-sm font-medium text-muted-foreground">No applications match these filters</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    A farmer who picks your association when registering appears here.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 border-t border-border pt-5">{{ $applications->links() }}</div>
    </x-ui.card>

    {{-- ---------- THE APPLICATION ---------- --}}
    <x-ui.detail-panel :selected="$selected" :back-url="$backUrl"
                       empty-text="Select an application from the list to review it.">
        @if ($selected)
            @php
                $farmer  = $selected;
                $status  = MembershipApplication::forFarmer($farmer);
                $ourTurn = MembershipApplication::isAssociationsTurn($farmer);
            @endphp

            <div class="mb-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    APP-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                </p>
                <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $farmer->full_name }}</h3>
                <div class="mt-2"><x-ui.status :value="$status" /></div>
                <p class="mt-2 text-xs text-muted-foreground">{{ MembershipApplication::means($status) }}</p>
            </div>

            {{-- The office's remarks, while the application is back on this
                 association's desk. Shown first and in amber because it is
                 the thing the officer has to act on: the office is waiting
                 for the farmer to be told. --}}
            @if ($status === MembershipApplication::RETURNED_TO_ASSOCIATION)
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm dark:border-amber-800 dark:bg-amber-950/50">
                    <p class="font-semibold text-amber-900 dark:text-amber-200">
                        Returned to your association by the Municipal Agriculture Office
                        @if ($farmer->mao_returned_at)
                            on {{ $farmer->mao_returned_at->format('M d, Y') }}
                        @endif
                    </p>
                    <p class="mt-1 text-amber-900 dark:text-amber-100">{{ $farmer->mao_return_note }}</p>
                    <p class="mt-1.5 text-xs text-amber-800 dark:text-amber-300">
                        Review these remarks, then return the application to the farmer for correction.
                        It comes back to you once they have fixed it.
                    </p>
                </div>
            @endif

            {{-- The return note, when there is one. Kept visible after a
                 resubmission on purpose: an officer judging whether the fix
                 answers the question needs to see what was asked. --}}
            @if ($farmer->return_note)
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm dark:border-red-900 dark:bg-red-950/50">
                    <p class="font-semibold text-red-800 dark:text-red-300">
                        Last returned for correction
                        @if ($farmer->returned_by_role === 'mao')
                            by the Municipal Agriculture Office
                        @else
                            by your association
                        @endif
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
                    </dl>
                </div>

                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Ownership</p>
                    <dl class="space-y-1.5">
                        <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Farmer Type</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</dd></div>
                        @if ($farmer->ownership_type === 'tenant')
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Land Owner</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->landowner_name ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Owner Contact</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->landowner_contact ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">Owner Location</dt><dd class="min-w-0 text-right font-medium text-foreground">{{ $farmer->landowner_location ?? '-' }}</dd></div>
                        @else
                            <div class="flex justify-between gap-3">
                                <dt class="shrink-0 text-muted-foreground">Barangay Certificate</dt>
                                <dd class="min-w-0 text-right font-medium text-foreground">
                                    @if ($farmer->barangay_certificate_path)
                                        <a href="{{ $farmer->barangay_certificate_url }}"
                                           target="_blank" rel="noopener" class="text-primary hover:underline">View</a>
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
                                    {{ $mc->crop->name }}{{ $mc->crop_specify ? " ({$mc->crop_specify})" : '' }}@if (! $loop->last), @endif
                                @empty - @endforelse
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Who has handled it so far. Only the lines that have
                     actually happened: an application forwarded before this
                     workflow existed has no officer and no date recorded,
                     and a blank "Forwarded by -" would be inventing one. --}}
                @if ($farmer->associationReviewer || $farmer->forwarded_at)
                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Handling</p>
                        <dl class="space-y-1.5">
                            @if ($farmer->associationReviewer)
                                <div class="flex justify-between gap-3">
                                    <dt class="shrink-0 text-muted-foreground">Review started by</dt>
                                    <dd class="min-w-0 text-right font-medium text-foreground">
                                        {{ $farmer->associationReviewer->display_name }}
                                        @if ($farmer->association_review_started_at)
                                            <span class="block text-xs font-normal text-muted-foreground">
                                                {{ $farmer->association_review_started_at->format('M d, Y g:i A') }}
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            @if ($farmer->forwarded_at)
                                <div class="flex justify-between gap-3">
                                    <dt class="shrink-0 text-muted-foreground">Verified &amp; forwarded</dt>
                                    <dd class="min-w-0 text-right font-medium text-foreground">
                                        {{ $farmer->forwardedBy?->display_name ?? 'Recorded before this was tracked' }}
                                        <span class="block text-xs font-normal text-muted-foreground">
                                            {{ $farmer->forwarded_at->format('M d, Y g:i A') }}
                                        </span>
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        {{-- What this association told the office. Shown back
                             to the officer so they can see what they vouched
                             for, which matters when the office rings up about
                             it weeks later. --}}
                        @if ($farmer->forward_note)
                            <div class="mt-3 rounded-lg bg-muted px-3 py-2.5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Your verification remarks
                                </p>
                                <p class="mt-1 text-sm text-foreground">{{ $farmer->forward_note }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ---------- ACTIONS ----------
                 Only while the application is still with this association.
                 The controller checks the same thing again, because a page
                 drawn five minutes ago still has its buttons. --}}
            @if ($ourTurn)
                <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4">
                    @if ($status === MembershipApplication::SUBMITTED)
                        <button type="button" @click="acting = 'review'"
                                class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                            Start Review
                        </button>
                        <p class="text-xs text-muted-foreground">
                            Starting the review records that you are looking at this application, so the
                            office can see it is being handled.
                        </p>
                    @elseif ($status === MembershipApplication::RETURNED_TO_ASSOCIATION)
                        {{-- The office sent it back, so the only move is to
                             pass the correction on to the farmer. Forward is
                             not offered: it would put the same application
                             back in front of the office with nothing fixed.
                             The controller refuses it too. --}}
                        <button type="button" @click="acting = 'return'"
                                class="w-full rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Return to Farmer
                        </button>
                        <p class="text-xs text-muted-foreground">
                            The office's remarks are filled in for you. Reword them if the farmer needs
                            it said more simply.
                        </p>
                    @else
                        {{-- "Verify and forward" rather than "Forward": the
                             revision names the officer's action that way, and
                             it is the more accurate label. Forwarding IS the
                             verification here - there is no state where an
                             application is verified but still sitting on this
                             desk, which is why there is no separate Verify
                             button and no verified column. --}}
                        <button type="button" @click="acting = 'forward'"
                                class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                            Verify &amp; Forward to MAO
                        </button>
                        <button type="button" @click="acting = 'return'"
                                class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950/50">
                            Return to Farmer
                        </button>
                    @endif
                </div>
            @elseif ($status === MembershipApplication::FORWARDED)
                <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                    This application is with the Municipal Agriculture Office. You can see it here, but
                    only the office can approve, reject or return it now.
                </p>
            @elseif ($status === MembershipApplication::RETURNED)
                <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                    Waiting on the farmer. It comes back to your association once they have corrected it.
                </p>
            @else
                <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                    The Municipal Agriculture Office has already decided this application.
                </p>
            @endif

            {{-- ---------- CONFIRMATIONS (revision section 91) ----------
                 Nothing here is one click. Each dialog repeats what is about
                 to happen and what it cannot be taken back from. --}}

            {{-- Start Review --}}
            <div x-show="acting === 'review'" x-cloak
                 class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                    <h3 class="mb-2 text-lg font-semibold text-foreground">Start reviewing this application?</h3>
                    <p class="mb-5 text-sm text-muted-foreground">
                        This records that you are reviewing <strong>{{ $farmer->full_name }}</strong>'s
                        application. Nothing is sent to the farmer or the office yet.
                    </p>
                    <div class="flex gap-3">
                        <button type="button" @click="acting = null"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Cancel
                        </button>
                        <form method="POST" class="flex-1"
                              action="{{ route('association.membership-applications.start-review', $farmer) }}">
                            @csrf @method('PUT')
                            <button class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                                Start Review
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Forward --}}
            <div x-show="acting === 'forward'" x-cloak
                 class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                    <h3 class="mb-2 text-lg font-semibold text-foreground">Verify and forward this application?</h3>
                    <p class="mb-3 text-sm text-muted-foreground">
                        You are telling the Municipal Agriculture Office that
                        <strong>{{ $farmer->full_name }}</strong> is one of your members and that their
                        details look right.
                    </p>
                    <ul class="mb-5 space-y-1 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        <li><strong class="text-foreground">Barangay:</strong> {{ $farmer->barangay->name ?? '-' }}</li>
                        <li><strong class="text-foreground">Farmer type:</strong> {{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</li>
                        <li><strong class="text-foreground">Farm size:</strong> {{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : 'Not given' }}</li>
                        <li><strong class="text-foreground">Main crops:</strong> {{ $farmer->mainCrops->map(fn ($mc) => $mc->crop->name)->join(', ') ?: 'None listed' }}</li>
                    </ul>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Once forwarded you can still see the application, but you will not be able to
                        change or return it.
                    </p>

                    {{-- The form now wraps the remarks field as well as the
                         button, so the note is submitted with the action.
                         It used to wrap the button alone. --}}
                    <form method="POST"
                          action="{{ route('association.membership-applications.forward', $farmer) }}">
                        @csrf @method('PUT')

                        {{-- OPTIONAL, UNLIKE THE RETURN NOTE
                             A return has to say what is wrong, because the
                             farmer cannot act without knowing. Most forwards
                             are routine and have nothing to add, and a
                             required field on a routine action collects a
                             column of "ok" that teaches the office to stop
                             reading it. The office's screen says "No remarks
                             given" when the field is left empty. --}}
                        <label for="forward_note" class="mb-1 block text-sm font-medium text-foreground">
                            Verification remarks
                            <span class="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <textarea id="forward_note" name="forward_note" rows="3" maxlength="1000"
                                  placeholder="Anything the office should know. For example: long-standing member, certificate checked against our own records."
                                  class="mb-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">{{ old('forward_note') }}</textarea>
                        <p class="mb-4 text-xs text-muted-foreground">
                            The office reads this beside the application. Leave it blank if there is nothing to add.
                        </p>

                        <div class="flex gap-3">
                            <button type="button" @click="acting = null"
                                    class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                                Confirm &amp; Forward
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Return for Correction --}}
            <div x-show="acting === 'return'" x-cloak
                 class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
                <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                    <h3 class="mb-2 text-lg font-semibold text-foreground">Return this application to the farmer?</h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        {{ $farmer->full_name }} will be asked to correct their registration and submit it
                        again. Tell them what needs fixing; they see this note word for word.
                        @if ($status === MembershipApplication::RETURNED_TO_ASSOCIATION)
                            The office's remarks are filled in below.
                        @endif
                    </p>
                    <form method="POST"
                          action="{{ route('association.membership-applications.return', $farmer) }}">
                        @csrf @method('PUT')
                        <label for="return_note" class="mb-1 block text-sm font-medium text-foreground">
                            What needs fixing <span class="text-red-600">*</span>
                        </label>
                        <textarea id="return_note" name="return_note" rows="4" required minlength="10" maxlength="1000"
                                  placeholder="For example: the barangay certificate is for a different person, please upload your own."
                                  class="mb-2 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary">{{ old('return_note', $status === MembershipApplication::RETURNED_TO_ASSOCIATION ? $farmer->mao_return_note : '') }}</textarea>
                        <p class="mb-4 text-xs text-muted-foreground">At least 10 characters.</p>
                        <div class="flex gap-3">
                            <button type="button" @click="acting = null"
                                    class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="flex-1 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                Confirm &amp; Return to Farmer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui.detail-panel>
</div>
@endsection
