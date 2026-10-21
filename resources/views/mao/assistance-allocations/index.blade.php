@extends('layouts.app')

@section('title', 'Assistance Allocation')
@section('heading', 'Assistance Allocation')
@section('subheading', "Generate and manage assistance allocation to farmer associations based on MAO-approved beneficiary lists.")

@php
    /* The Allocate modal asks one question now: WHO (Oct 2026, client
       decision). The association, the disaster, the assistance item, the
       start date and the crop all came off it. The office picks farmers
       from every verified report in the municipality; what they are given
       is "assistance", with no name, no type and no figure; and the date is
       simply the day it is allocated, because the office cannot promise a
       collection date and the association arranges that afterwards.

       The checklist no longer waits on an association being chosen, so the
       JSON endpoint that filled it is gone and $eligible is rendered with
       the page. */
    $viewUrl  = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl  = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
{{-- ?allocate=1 opens the Allocate modal on arrival. The standalone
     /create page was retired (there were two copies of one form); its route
     now redirects here with that flag, so an old bookmark still ends up at
     the form rather than at a page with the form hidden behind a button. --}}
<div x-data="{ action: null, deciding: null }"
     @if (request()->boolean('allocate'))
         x-init="$nextTick(() => $dispatch('open-dialog', 'allocate-assistance'))"
     @endif>

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- ===================== STAT CARDS =====================

         Deliberately above the list/detail split below, so they run the full
         width of the page the way they do on Validation Monitoring and every
         other MAO screen that has both stat cards and a detail panel.

         They used to sit inside the left column of that split. That squeezed
         them into about two thirds of the width and, because the panel is
         lg:items-start, stranded the detail panel at the top right beside
         them as a tall empty box, with the list it belongs to starting far
         below it.
    --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- THE FIRST CARD CHANGED MEANING (Oct 2026)

             It used to read "Total Verified Farmers", counting reports a
             technician had inspected but the office had not yet decided.
             Eligibility now starts at verified rather than at approved
             (client decision), so those farmers are eligible and belong in
             the second card. Counting them in both would put back the exact
             double-count section 31 fixed.

             So the first card moved one step earlier in the pipeline: the
             reports still being inspected. The two cards are disjoint by
             construction again, and they still read left to right as the
             queue feeding the eligible list. --}}
        <x-ui.stat label="Awaiting Inspection" :value="number_format($stats['awaiting_inspection'])"
                   :hint="'Not yet inspected · from ' . number_format($stats['awaiting_from_associations']) . ' association' . ($stats['awaiting_from_associations'] === 1 ? '' : 's')"
                   tone="primary"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9M16 2.7a4 4 0 010 7.6" />

        <x-ui.stat label="Qualified Beneficiaries" :value="number_format($stats['qualified_beneficiaries'])"
                   hint="Inspected by a technician, belongs to an association, and not already in an allocation" tone="primary"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-ui.stat label="Pending Allocation" :value="number_format($stats['pending_allocation'])"
                   hint="Associations still needing MAO action" tone="warning"
                   icon="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-ui.stat label="Total Associations" :value="number_format($stats['total_associations'])"
                   hint="With an allocation for this selection"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM4 12.5a2 2 0 100-4 2 2 0 000 4zM20 12.5a2 2 0 100-4 2 2 0 000 4z" />
    </div>


    {{-- ===================== CASES AWAITING A DECISION =====================

         Section 11.2: the office must be able to mark a farmer Not
         Designated for Assistance. Designating already had a home - tick
         the farmer in Allocate Assistance and a beneficiary row is written -
         but refusing had none, so a farmer the office had considered and
         passed over sat in the queue forever, looking exactly like a farmer
         nobody had read yet, and never got an answer.

         These are reports a technician has finished with that have no
         decision and no live allocation against them. Four columns, like
         every other list (section 3.1); everything else about the case is
         behind View.

         The list is capped at ten rows on screen. It is a worklist, not an
         archive, and a hundred-row table here would push the allocation
         overview off the page.
    --}}
    @if ($awaitingDecision->isNotEmpty())
        <x-ui.card title="Cases Awaiting a Decision"
                   :description="$awaitingDecision->count() . ' ' . \Illuminate\Support\Str::plural('farmer', $awaitingDecision->count()) . ' inspected and waiting on the office'"
                   :padded="false" class="mb-5">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Report ID</th>
                            <th class="px-5 py-3 font-medium">Farmer</th>
                            <th class="px-5 py-3 text-center font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($awaitingDecision->take(10) as $case)
                            <tr class="hover:bg-muted/60">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-foreground">
                                    {{ $case->reference }}
                                </td>
                                <td class="px-5 py-3 font-medium text-foreground">
                                    {{ $case->farmer?->full_name ?? 'Unknown' }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <x-ui.status :value="\App\Support\AssistanceCase::PENDING_REVIEW" />
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button variant="view" size="sm"
                                                     :href="route('mao.damage-reports.index', ['selected' => $case->id])">
                                            View
                                        </x-ui.button>

                                        {{-- Designating is not a button here on purpose. A
                                             designation is part of an allocation - which
                                             assistance, how much, to which association - so
                                             it belongs in Allocate Assistance and nowhere
                                             else. This screen only records the "no". --}}
                                        <button type="button"
                                                @click="deciding = { id: {{ $case->id }}, reference: @js($case->reference), name: @js($case->farmer?->full_name ?? 'this farmer') }"
                                                class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                            Not Designated
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($awaitingDecision->count() > 10)
                <x-slot:footer>
                    <p class="text-xs text-muted-foreground">
                        Showing the 10 most recent of {{ number_format($awaitingDecision->count()) }}.
                        Allocating assistance clears a farmer from this list.
                    </p>
                </x-slot:footer>
            @endif
        </x-ui.card>
    @endif

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

    {{-- LIST (filters, overview table, recent activity and the Allocate
         Assistance modal) --}}
    <div>

    {{-- ===================== FILTERS + ACTIONS ===================== --}}
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        {{-- The "All Assistance Types" filter went with the assistance
             item (Oct 2026): an allocation names nothing to filter by. --}}
        <x-ui.filter-bar :fields="['disaster_id', 'association_id', 'status']" :tight="true">
            <x-ui.select name="disaster_id" placeholder="All Disasters" onchange="this.form.submit()" class="sm:w-52"
                         :selected="$filters['disaster_id']" :options="$disasters->pluck('name', 'id')" />

            <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()" class="sm:w-48"
                         :selected="$filters['association_id']" :options="$associations->pluck('name', 'id')" />

            <x-ui.select name="status" placeholder="All Statuses" onchange="this.form.submit()" class="sm:w-40"
                         :selected="$filters['status']"
                         :options="['allocated' => 'Allocated', 'pending' => 'Pending', 'not_eligible' => 'Not Eligible']" />
        </x-ui.filter-bar>

        <div class="flex shrink-0 flex-wrap items-center gap-3">
            {{-- A farmer saying "I did not receive this" only ever showed up
                 on the association's own screens before this was built.
                 This is the office-wide view of every one of those.
                 A plain text link here was too easy to miss - it is now a
                 real button, matching Export List and Allocate Assistance in
                 size and weight, and it switches to the destructive (red)
                 variant whenever there is something to actually look at. --}}
            {{-- Distribution Tracking used to be reachable only from the
                 phone's More sheet, and the revised More sheet (section 14)
                 does not list it. This button is now its door on every
                 device: it belongs beside Disputes anyway, since both are
                 the follow-up half of allocation. --}}
            <x-ui.button :href="route('mao.assistance-allocations.distribution-tracking')" variant="outline">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8"/>
                </svg>
                Distribution Tracking
            </x-ui.button>

            <x-ui.button :href="route('mao.assistance-allocations.disputes')"
                         variant="{{ $disputeCount > 0 ? 'destructive' : 'outline' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                </svg>
                Disputes
                @if ($disputeCount > 0)
                    <span class="inline-flex items-center justify-center rounded-full bg-white/25 px-2 py-0.5 text-xs font-semibold">
                        {{ $disputeCount }}
                    </span>
                @endif
            </x-ui.button>

            <x-ui.button variant="outline" :href="route('mao.assistance-allocations.export', request()->query())">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                Export List
            </x-ui.button>

            <x-ui.button @click="$dispatch('open-dialog', 'allocate-assistance')">
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg>
                Allocate Assistance
            </x-ui.button>
        </div>
    </div>

    {{-- The "nothing in your catalogue yet" warning was here. There is no
         catalogue to be empty: an allocation does not name an item. --}}

    {{-- ===================== OVERVIEW + RECENT ===================== --}}
    <div class="grid gap-5 xl:grid-cols-3">

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm xl:col-span-2">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Association Allocation Overview</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    @if ($selectedDisaster)
                        For {{ $selectedDisaster->name }}
                    @else
                        Across all disaster events
                    @endif
                </p>
            </div>

            {{-- ---------- PHONE: one card per association ---------- --}}
                <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($overviewRows as $row)
                        <li>
                            @if ($row->allocation)
                                <x-ui.record-card :href="$viewUrl($row->allocation->id)"
                                                  :title="$row->association->name"
                                                  :subtitle="$row->association->barangay
                                                        ? 'Brgy. ' . $row->association->barangay->name
                                                        : 'Office barangay not set'"
                                                  :status="$row->status"
                                                  :fields="[
                                                      'Qualified beneficiaries' => $row->qualified_count,
                                                      'Allocation'              => 'AA-' . str_pad($row->allocation->id, 3, '0', STR_PAD_LEFT),
                                                  ]" />
                            @else
                                <div class="rounded-xl border border-border bg-card p-3.5 shadow-sm">
                                    <p class="truncate text-base font-bold leading-tight text-foreground">{{ $row->association->name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $row->association->barangay ? 'Brgy. ' . $row->association->barangay->name : 'Office barangay not set' }}
                                    </p>
                                    <p class="mt-3 border-t border-border pt-3 text-sm text-muted-foreground">
                                        <span class="font-semibold text-foreground">{{ $row->qualified_count }}</span>
                                        qualified {{ $row->qualified_count === 1 ? 'beneficiary' : 'beneficiaries' }}, nothing allocated yet.
                                        Use Allocate Assistance above.
                                    </p>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No associations yet</p>
                        </li>
                    @endforelse
                </ul>

                <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Association</th>
                            <th class="px-5 py-3 text-right font-medium">Qualified Beneficiaries</th>
                            <th class="px-5 py-3 text-center font-medium">Status</th>
                            <th class="px-5 py-3 text-center font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($overviewRows as $row)
                            <tr class="hover:bg-muted/60 {{ $row->allocation && $selected?->id === $row->allocation->id ? 'bg-muted/60' : '' }}">
                                <td class="px-5 py-3">
                                    <p class="font-bold text-foreground">{{ $row->association->name }}</p>
                                    @if ($row->association->barangay)
                                        <p class="text-xs text-muted-foreground">Brgy. {{ $row->association->barangay->name }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-foreground">{{ $row->qualified_count }}</td>
                                <td class="px-5 py-3 text-center">
                                    @php
                                        $badge = match ($row->status) {
                                            'allocated' => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
                                            'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
                                            default     => 'bg-secondary text-muted-foreground',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $badge }}">
                                        {{ ucwords(str_replace('_', ' ', $row->status)) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($row->allocation)
                                        <x-ui.button :href="$viewUrl($row->allocation->id)"
                                                     variant="view" size="sm">View</x-ui.button>
                                    @elseif ($row->qualified_count > 0)
                                        <button type="button"
                                                @click="$dispatch('preselect-association', {{ $row->association->id }}); $dispatch('open-dialog', 'allocate-assistance')"
                                                class="text-sm font-medium text-primary underline hover:brightness-110">
                                            Allocate
                                        </button>
                                    @else
                                        <span class="text-sm text-muted-foreground">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-14 text-center">
                                    <p class="text-sm font-medium text-muted-foreground">No associations to show</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Try a different disaster event or clear your filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($overviewRows->hasPages())
                <div class="border-t border-border px-6 py-4">{{ $overviewRows->links() }}</div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold uppercase tracking-wide text-foreground">Recent Allocations</h2>
                <a href="{{ route('mao.assistance-allocations.history') }}" class="text-sm font-medium text-primary hover:underline">
                    View All
                </a>
            </div>

            @if ($recentAllocations->isNotEmpty())
                <ul class="divide-y divide-border">
    @foreach ($recentAllocations as $allocation)
                        <li class="px-6 py-4 {{ $selected?->id === $allocation->id ? 'bg-muted/60' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                {{-- An allocation has no name and is not
                                     addressed to one association, so the
                                     reference leads and the associations it
                                     concerns are listed underneath, worked
                                     out from the farmers on it. --}}
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-foreground">
                                        AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $allocation->concerned_associations->pluck('name')->join(', ') ?: 'No association' }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ $allocation->allocated_at?->format('M d, Y') }}
                                        &middot; {{ $allocation->beneficiaries_count }} farmer{{ $allocation->beneficiaries_count === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex rounded px-2.5 py-1 text-xs font-medium
                                    {{ $allocation->status === 'cancelled' ? 'bg-secondary text-muted-foreground' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                                    {{ ucfirst($allocation->status) }}
                                </span>
                            </div>
                            <x-ui.button :href="$viewUrl($allocation->id)"
                                         variant="view" size="sm" class="mt-2">
                                View details
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-muted-foreground">No allocations yet</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== ALLOCATE ASSISTANCE MODAL ===================== --}}
    <x-ui.dialog name="allocate-assistance" size="xl" title="Allocate Assistance"
                 description="Choose the farmers who will receive this batch of assistance.">

        <form id="allocate-assistance-form" method="POST"
              action="{{ route('mao.assistance-allocations.store') }}" enctype="multipart/form-data"
              data-confirm="Please review the selected farmers and any attached files before allocating. The farmers and their associations are notified as soon as you confirm."
              data-confirm-title="Allocate this assistance?"
              data-confirm-action="Confirm & Allocate"
              data-confirm-review="auto"
              class="space-y-5"
              x-data="{
                  /* The whole eligible list, rendered with the page rather
                     than fetched: nothing has to be chosen before it can be
                     shown. */
                  beneficiaries: @js($eligible),
                  selected: [],
                  files: [],
                  search: '',

                  /* The list can be the whole municipality, so a search box
                     earns its place here in a way it did not when the list
                     was one association's members. Filtering only changes
                     what is VISIBLE: a farmer ticked and then filtered out
                     of view stays selected, which is why selected is never
                     touched here. */
                  visible() {
                      const term = this.search.trim().toLowerCase();

                      if (! term) { return this.beneficiaries; }

                      return this.beneficiaries.filter((b) =>
                          b.name.toLowerCase().includes(term)
                          || b.barangay.toLowerCase().includes(term)
                          || b.association.toLowerCase().includes(term)
                          || b.reference.toLowerCase().includes(term));
                  },

                  /* Select All acts on what is on screen, not on everything,
                     because that is what somebody who has just filtered the
                     list means by it. Clearing works the same way. */
                  toggleAllVisible() {
                      const ids = this.visible().map((b) => b.farmer_id);
                      const allShown = ids.every((id) => this.selected.includes(id));

                      this.selected = allShown
                          ? this.selected.filter((id) => ! ids.includes(id))
                          : [...new Set([...this.selected, ...ids])];
                  },

                  toggleAll() {
                      this.selected = (this.selected.length === this.beneficiaries.length && this.beneficiaries.length > 0)
                          ? []
                          : this.beneficiaries.map((b) => b.farmer_id);
                  },

                  onFilesSelected(event) {
                      this.files = Array.from(event.target.files);
                  },

                  removeFile(index) {
                      const input = this.$refs.documentsInput;
                      const transfer = new DataTransfer();

                      this.files.forEach((file, i) => { if (i !== index) { transfer.items.add(file); } });

                      input.files = transfer.files;
                      this.files = Array.from(input.files);
                  },

                  /* An allocation IS its list of farmers, so one ticked
                     name is the whole requirement. */
                  ready() {
                      return this.selected.length > 0;
                  },
              }"
              {{-- The overview table's "Allocate" button still points at one
                   association. It cannot preselect an association any more,
                   because there is no association field, so it preselects
                   that association's eligible members instead, which is what
                   an officer clicking it from that row actually wants. --}}
              @preselect-association.window="
                  search = '';
                  selected = beneficiaries
                      .filter((b) => String(b.association_id) === String($event.detail))
                      .map((b) => b.farmer_id)">
            @csrf

            {{-- ---------- 1. Select Beneficiaries ---------- --}}
            <div>
                <p class="text-sm font-semibold text-foreground">1. Select the farmers</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Everyone here has a damage report a technician has verified, and is not already on another
                    allocation. Their association is shown so you can see who will have to be told.
                </p>

                <div class="mt-3">
                    <x-ui.input type="search" x-model="search" placeholder="Search by name, barangay, association or report" />
                </div>

                <div class="mt-2 overflow-hidden rounded-lg border border-border">
                    <div class="flex items-center justify-between border-b border-border bg-muted px-4 py-2.5">
                        <span class="text-sm text-muted-foreground">
                            Selected: <span class="font-semibold text-foreground" x-text="selected.length"></span>
                            / <span x-text="beneficiaries.length"></span>
                            <span x-show="search.trim()" x-cloak class="text-xs">
                                (showing <span x-text="visible().length"></span>)
                            </span>
                        </span>
                        <x-ui.button type="button" size="sm" variant="outline" @click="toggleAllVisible()"
                                     x-show="visible().length > 0">
                            <span x-text="visible().every((b) => selected.includes(b.farmer_id)) ? 'Clear shown' : 'Select shown'"></span>
                        </x-ui.button>
                    </div>

                    <div class="max-h-56 overflow-y-auto">
                        <p x-show="beneficiaries.length === 0" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            No farmer qualifies right now. A farmer appears here once a technician has verified
                            their damage report, and drops off once they are on an allocation.
                        </p>
                        <p x-show="beneficiaries.length > 0 && visible().length === 0" x-cloak
                           class="px-4 py-6 text-center text-sm text-muted-foreground">
                            No farmer matches that search.
                        </p>

                        <table class="w-full text-left text-sm" x-show="visible().length > 0">
                            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2"></th>
                                    <th class="px-4 py-2">Name</th>
                                    <th class="px-4 py-2">Barangay</th>
                                    <th class="px-4 py-2">Association</th>
                                    <th class="px-4 py-2">Report</th>
                                    <th class="px-4 py-2">Damage</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template x-for="b in visible()" :key="b.farmer_id">
                                    <tr>
                                        <td class="px-4 py-2">
                                            <input type="checkbox" :value="b.farmer_id" x-model="selected" data-confirm-skip
                                                   class="h-4 w-4 rounded border-input text-primary focus:ring-ring">
                                        </td>
                                        <td class="px-4 py-2 font-medium text-foreground" x-text="b.name"></td>
                                        <td class="px-4 py-2 text-muted-foreground" x-text="b.barangay"></td>
                                        <td class="px-4 py-2 text-muted-foreground" x-text="b.association"></td>
                                        <td class="px-4 py-2 text-muted-foreground" x-text="b.reference"></td>
                                        <td class="px-4 py-2 text-muted-foreground" x-text="b.severity_label"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- The actual submitted list. A plain, visible (but small) field
                     so the auto-generated review step shows a real summary
                     rather than nothing at all - hidden inputs are skipped by
                     the review builder on purpose. --}}
                <input type="text" readonly tabindex="-1" data-confirm-label="Farmers selected"
                       class="sr-only" :value="selected.length + ' of ' + beneficiaries.length + ' eligible farmer(s)'">

                <template x-for="id in selected" :key="'beneficiary-' + id">
                    <input type="hidden" name="beneficiary_ids[]" :value="id">
                </template>
            </div>

            {{-- ---------- 2. Remarks ----------

                 This whole step used to ask for the assistance item, the
                 type, the quantity, the start date and the crop. All of it
                 went in Oct 2026; see the note at the top of this file.
                 What is left is the one optional note. --}}
            <div class="border-t border-border pt-5">
                <p class="text-sm font-semibold text-foreground">2. Remarks <span class="font-normal text-muted-foreground">(optional)</span></p>

                <div class="mt-3">
                    <x-ui.textarea name="remarks" rows="2"
                                   placeholder="Anything the office should record about this batch."
                                   maxlength="1000">{{ old('remarks') }}</x-ui.textarea>
                </div>
            </div>

            {{-- WHO GETS TOLD, SAID BEFORE THEY CONFIRM
                 An officer allocating is about to send messages to farmers
                 and to association officers. That should not be a surprise
                 discovered afterwards. --}}
            <div class="rounded-lg border border-primary/30 bg-accent px-4 py-3 text-sm text-accent-foreground">
                <p class="font-medium">Everyone selected is notified as soon as you confirm.</p>
                <p class="mt-1 text-xs">
                    Each farmer is told they have been selected and that there is no collection date yet. Their
                    association is told which of its members to contact, and arranges when they collect it.
                </p>
            </div>

            {{-- ---------- 3. Upload Supporting Documents ---------- --}}
            <div class="border-t border-border pt-5">
                <p class="text-sm font-semibold text-foreground">3. Upload Supporting Documents <span class="font-normal text-muted-foreground">(optional)</span></p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    You may attach the MAO endorsement letter, allocation memo, or other relevant files.
                </p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-input px-4 py-8 text-center hover:border-primary/50">
                        <svg class="mb-2 h-8 w-8 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="1.6"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 16V4m0 0L7 9m5-5l5 5M5 20h14"/>
                        </svg>
                        <span class="text-sm text-muted-foreground">
                            Drag and drop files here, or <span class="font-medium text-primary">click to browse</span>
                        </span>
                        <span class="mt-1 text-xs text-muted-foreground">PDF, JPG, PNG (Max 10MB per file)</span>
                        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                               x-ref="documentsInput" @change="onFilesSelected($event)" class="hidden">
                    </label>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Attached Files</p>
                        <p x-show="files.length === 0" class="text-sm text-muted-foreground">No files attached yet.</p>
                        <ul class="space-y-2" x-show="files.length > 0">
                            <template x-for="(file, index) in files" :key="file.name + file.size">
                                <li class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/>
                                        </svg>
                                        <span class="truncate" x-text="file.name"></span>
                                    </span>
                                    <button type="button" @click="removeFile(index)"
                                            class="shrink-0 text-muted-foreground hover:text-destructive">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M6 6l12 12M18 6L6 18"/>
                                        </svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="outline" @click="$dispatch('close-dialog', 'allocate-assistance')">Cancel</x-ui.button>
            <x-ui.button type="submit" form="allocate-assistance-form">
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                Allocate Assistance
            </x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    </div>{{-- /LIST --}}

    {{-- DETAIL --}}
    <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                        empty-text="Select an association's allocation from the list to view its details.">
        @if ($selected)
            @include('mao.assistance-allocations._allocation-detail', ['allocation' => $selected])
        @endif
    </x-ui.detail-panel>

    </div>{{-- /lg:grid --}}

    {{-- ===================== NOT DESIGNATED: REVIEW AND CONFIRM =====================

         Section 91: no important transaction completes on the first click.
         The officer sees who they are refusing, has to write the reason, and
         then confirms. The reason is required (the form will not submit
         without it) because it is the only thing that will be left to
         explain the decision later, and because the farmer is shown it.
    --}}
    <div x-show="deciding" x-cloak
         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4"
         @keydown.escape.window="deciding = null">
        <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-1 text-lg font-semibold text-foreground">Not designated for assistance?</h3>

            <p class="mb-4 text-sm text-muted-foreground">
                <strong x-text="deciding?.name"></strong> will be recorded as NOT designated for
                assistance on report <span x-text="deciding?.reference"></span>, and will be told so
                along with the reason you give. The damage report itself is not changed, and the
                decision can be revisited until assistance has actually been received.
            </p>

            <form method="POST" :action="`{{ url('mao/assistance-decisions') }}/${deciding?.id}`">
                @csrf @method('PUT')
                <input type="hidden" name="decision" value="not_designated">

                <label for="not-designated-reason" class="mb-1 block text-sm font-medium text-foreground">
                    Reason <span class="text-destructive">*</span>
                </label>
                <textarea id="not-designated-reason" name="remarks" rows="3" required maxlength="1000"
                          placeholder="For example: allocation for this disaster was fully distributed."
                          class="mb-4 w-full rounded-lg border border-input bg-card px-3 py-2 text-sm
                                 placeholder:text-muted-foreground focus:border-primary focus:outline-none"></textarea>

                <div class="flex gap-3">
                    <button type="button" @click="deciding = null"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:brightness-110">
                        Confirm Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
