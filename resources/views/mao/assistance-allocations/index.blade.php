@extends('layouts.app')

@section('title', 'Assistance Allocation')
@section('heading', 'Assistance Allocation')
@section('subheading', "Generate and manage assistance allocation to farmer associations based on MAO-approved beneficiary lists.")

@php
    $newValue = \App\Http\Controllers\MAO\AssistanceAllocationController::NEW_ASSISTANCE;
@endphp

@section('content')
<div>

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- ===================== STAT CARDS ===================== --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Total Verified Farmers" :value="number_format($stats['total_verified_farmers'])"
                   :hint="'Awaiting MAO decision · from ' . number_format($stats['verified_from_associations']) . ' association' . ($stats['verified_from_associations'] === 1 ? '' : 's')"
                   tone="primary"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9M16 2.7a4 4 0 010 7.6" />

        <x-ui.stat label="Qualified Beneficiaries" :value="number_format($stats['qualified_beneficiaries'])"
                   hint="Approved by MAO, belongs to an association, and not already in an allocation" tone="primary"
                   icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-ui.stat label="Pending Allocation" :value="number_format($stats['pending_allocation'])"
                   hint="Associations still needing MAO action" tone="warning"
                   icon="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />

        <x-ui.stat label="Total Associations" :value="number_format($stats['total_associations'])"
                   hint="With an allocation for this selection"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM4 12.5a2 2 0 100-4 2 2 0 000 4zM20 12.5a2 2 0 100-4 2 2 0 000 4z" />
    </div>

    {{-- ===================== FILTERS + ACTIONS ===================== --}}
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <x-ui.select name="disaster_id" placeholder="All Disasters" class="min-w-52"
                         :selected="$filters['disaster_id']" :options="$disasters->pluck('name', 'id')" />

            <x-ui.select name="association_id" placeholder="All Associations" class="min-w-48"
                         :selected="$filters['association_id']" :options="$associations->pluck('name', 'id')" />

            <x-ui.select name="assistance_id" placeholder="All Assistance Types" class="min-w-48"
                         :selected="$filters['assistance_id']" :options="$assistances->pluck('name', 'id')" />

            <x-ui.select name="status" placeholder="All Statuses" class="min-w-40" :selected="$filters['status']"
                         :options="['allocated' => 'Allocated', 'pending' => 'Pending', 'not_eligible' => 'Not Eligible']" />

            <x-ui.button type="submit">Filter</x-ui.button>

            @if (request()->hasAny(['disaster_id', 'association_id', 'assistance_id', 'status']))
                <a href="{{ route('mao.assistance-allocations.index') }}"
                   class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </form>

        <div class="flex shrink-0 flex-wrap items-center gap-3">
            {{-- A farmer saying "I did not receive this" only ever showed up
                 on the association's own screens before this was built.
                 This is the office-wide view of every one of those.
                 A plain text link here was too easy to miss - it is now a
                 real button, matching Export List and Allocate Assistance in
                 size and weight, and it switches to the destructive (red)
                 variant whenever there is something to actually look at. --}}
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

    @if ($assistances->isEmpty())
        <x-ui.alert variant="warning" title="No assistance in your catalogue yet" class="mb-5">
            You can still create one on the fly from the Allocate Assistance form, or add one under
            <a href="{{ route('mao.assistance.index') }}" class="font-medium underline">Assistance Catalogue</a> first.
        </x-ui.alert>
    @endif

    {{-- ===================== OVERVIEW + RECENT ===================== --}}
    <div class="grid gap-5 xl:grid-cols-3">

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm xl:col-span-2">
            <div class="border-b border-border px-6 py-4">
                <h2 class="text-base font-semibold text-foreground">Association Allocation Overview</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    @if ($selectedDisaster)
                        For {{ $selectedDisaster->name }}
                    @else
                        Across all disaster events
                    @endif
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Association</th>
                            <th class="px-5 py-3 text-right font-medium">Qualified Beneficiaries</th>
                            <th class="px-5 py-3 font-medium">Assistance Type</th>
                            <th class="px-5 py-3 text-center font-medium">Status</th>
                            <th class="px-5 py-3 text-center font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($overviewRows as $row)
                            <tr class="hover:bg-muted/60">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-foreground">{{ $row->association->name }}</p>
                                    @if ($row->association->barangay)
                                        <p class="text-xs text-muted-foreground">Brgy. {{ $row->association->barangay->name }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-foreground">{{ $row->qualified_count }}</td>
                                <td class="px-5 py-3 text-muted-foreground">{{ $row->allocation?->assistance?->name ?? '—' }}</td>
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
                                        <x-ui.button :href="route('mao.assistance-allocations.show', $row->allocation)"
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
                                <td colspan="5" class="px-5 py-14 text-center">
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
                <h2 class="text-base font-semibold text-foreground">Recent Allocations</h2>
                <a href="{{ route('mao.assistance-allocations.history') }}" class="text-sm font-medium text-primary hover:underline">
                    View All
                </a>
            </div>

            @if ($recentAllocations->isNotEmpty())
                <ul class="divide-y divide-border">
                    @foreach ($recentAllocations as $allocation)
                        <li class="px-6 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ $allocation->association?->name ?? '-' }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ $allocation->assistance?->name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ $allocation->allocated_at?->format('M d, Y') }}
                                        &middot; {{ $allocation->beneficiaries_count }} beneficiar{{ $allocation->beneficiaries_count === 1 ? 'y' : 'ies' }}
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex rounded px-2.5 py-1 text-xs font-medium
                                    {{ $allocation->status === 'cancelled' ? 'bg-secondary text-muted-foreground' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                                    {{ ucfirst($allocation->status) }}
                                </span>
                            </div>
                            <x-ui.button :href="route('mao.assistance-allocations.show', $allocation)"
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
    <x-ui.dialog name="allocate-assistance" size="xl" title="Allocate Assistance to Association"
                 description="Select the beneficiaries and specify the assistance details for the association.">

        <form id="allocate-assistance-form" method="POST"
              action="{{ route('mao.assistance-allocations.store') }}" enctype="multipart/form-data"
              data-confirm="Please review the association, beneficiaries, assistance details, and any attached files before allocating."
              data-confirm-title="Allocate this assistance?"
              data-confirm-action="Confirm & Allocate"
              data-confirm-review="auto"
              class="space-y-5"
              x-data="{
                  associationId: '',
                  disasterId: @js((string) old('disaster_id', $filters['disaster_id'] ?: '')),
                  assistanceId: @js((string) old('assistance_id', '')),
                  cropId: @js((string) old('crop_id', '')),
                  newName: @js(old('new_assistance_name', '')),
                  newType: @js(old('new_assistance_type', 'in_kind')),
                  newDescription: @js(old('new_assistance_description', '')),
                  newAvailable: @js((string) old('new_assistance_available', '')),

                  beneficiaries: [],
                  selected: [],
                  loadingBeneficiaries: false,
                  beneficiaryError: '',
                  files: [],

                  lookup: {
                      types: @js($assistances->pluck('type', 'id')),
                  },

                  makingNew() { return this.assistanceId === @js($newValue); },
                  isCash() {
                      return this.makingNew() ? this.newType === 'cash' : this.lookup.types[this.assistanceId] === 'cash';
                  },

                  fetchBeneficiaries() {
                      this.selected = [];
                      this.beneficiaries = [];
                      this.beneficiaryError = '';

                      // Disaster is optional here too (section 29): the
                      // association alone is enough to pull up every
                      // MAO-approved, not-yet-allocated farmer in it (section
                      // 31). Picking a disaster just narrows that same list
                      // to reports citing it.
                      if (! this.associationId) {
                          return;
                      }

                      this.loadingBeneficiaries = true;

                      let url = @js(route('mao.assistance-allocations.eligible-beneficiaries'))
                              + '?association_id=' + this.associationId;

                      if (this.disasterId) {
                          url += '&disaster_id=' + this.disasterId;
                      }

                      fetch(url, { headers: { Accept: 'application/json' } })
                          .then((response) => {
                              if (! response.ok) { throw new Error('request failed'); }
                              return response.json();
                          })
                          .then((data) => {
                              this.beneficiaries = data.beneficiaries;
                              this.selected = data.beneficiaries.map((b) => b.farmer_id);
                          })
                          .catch(() => {
                              this.beneficiaryError = 'Could not load the beneficiary list. Please try again.';
                          })
                          .finally(() => { this.loadingBeneficiaries = false; });
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

                  ready() {
                      if (! this.assistanceId || ! this.associationId) { return false; }
                      if (this.makingNew() && ! this.newName.trim()) { return false; }
                      return true;
                  },
              }"
              @preselect-association.window="associationId = String($event.detail); fetchBeneficiaries()">
            @csrf
            <input type="hidden" name="allocation_source" value="modal">

            {{-- ---------- Association + Disaster ---------- --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Association" name="association_id" required>
                    <x-ui.select name="association_id" placeholder="Select association" required
                                 x-model="associationId" @change="fetchBeneficiaries()"
                                 :options="$associations->pluck('name', 'id')" />
                </x-ui.field>

                <x-ui.field label="Disaster Event" name="disaster_id"
                            hint="Optional. The checklist below already includes every MAO-approved farmer in the association who isn't already covered by another allocation; picking an event here just narrows it to reports citing that event specifically.">
                    <x-ui.select name="disaster_id" placeholder="Select disaster event (optional)"
                                 x-model="disasterId" @change="fetchBeneficiaries()"
                                 :options="$disasters->pluck('name', 'id')" />
                </x-ui.field>
            </div>

            {{-- ---------- 1. Select Beneficiaries ---------- --}}
            <div>
                <p class="text-sm font-semibold text-foreground">1. Select Beneficiaries</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Choose the qualified members who will receive the assistance.</p>

                <div class="mt-2 overflow-hidden rounded-lg border border-border">
                    <div class="flex items-center justify-between border-b border-border bg-muted px-4 py-2.5">
                        <span class="text-sm text-muted-foreground">
                            Selected: <span class="font-semibold text-foreground" x-text="selected.length"></span>
                            / <span x-text="beneficiaries.length"></span>
                        </span>
                        <x-ui.button type="button" size="sm" variant="outline" @click="toggleAll()"
                                     x-show="beneficiaries.length > 0">
                            <span x-text="selected.length === beneficiaries.length ? 'Clear All' : 'Select All'"></span>
                        </x-ui.button>
                    </div>

                    <div class="max-h-56 overflow-y-auto">
                        <p x-show="loadingBeneficiaries" class="px-4 py-6 text-center text-sm text-muted-foreground">
                            Loading eligible farmers...
                        </p>
                        <p x-show="! loadingBeneficiaries && beneficiaryError" x-text="beneficiaryError"
                           class="px-4 py-6 text-center text-sm text-destructive"></p>
                        <p x-show="! loadingBeneficiaries && ! beneficiaryError && ! associationId"
                           class="px-4 py-6 text-center text-sm text-muted-foreground">
                            Select an association to see who qualifies.
                        </p>
                        <p x-show="! loadingBeneficiaries && ! beneficiaryError && associationId && beneficiaries.length === 0"
                           class="px-4 py-6 text-center text-sm text-muted-foreground">
                            <span x-show="disasterId">No MAO-approved farmers in this association qualify for this disaster event yet - they may still be awaiting a decision, or already covered by another allocation.</span>
                            <span x-show="! disasterId">No MAO-approved farmers in this association qualify yet - they may still be awaiting a decision, or already covered by another allocation.</span>
                        </p>

                        <table class="w-full text-left text-sm" x-show="! loadingBeneficiaries && beneficiaries.length > 0">
                            <thead class="bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-2"></th>
                                    <th class="px-4 py-2">Name</th>
                                    <th class="px-4 py-2">Barangay</th>
                                    <th class="px-4 py-2">Damage Type</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template x-for="b in beneficiaries" :key="b.farmer_id">
                                    <tr>
                                        <td class="px-4 py-2">
                                            <input type="checkbox" :value="b.farmer_id" x-model="selected" data-confirm-skip
                                                   class="h-4 w-4 rounded border-input text-primary focus:ring-ring">
                                        </td>
                                        <td class="px-4 py-2 font-medium text-foreground" x-text="b.name"></td>
                                        <td class="px-4 py-2 text-muted-foreground" x-text="b.barangay"></td>
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
                <input type="text" readonly tabindex="-1" data-confirm-label="Beneficiaries Selected"
                       class="sr-only" :value="selected.length + ' of ' + beneficiaries.length + ' qualified farmer(s)'">

                <template x-for="id in selected" :key="'beneficiary-' + id">
                    <input type="hidden" name="beneficiary_ids[]" :value="id">
                </template>
            </div>

            {{-- ---------- 2. Assistance Details ---------- --}}
            <div class="border-t border-border pt-5">
                <p class="text-sm font-semibold text-foreground">2. Assistance Details</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Specify the type of assistance and additional information.</p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="sm:col-span-2 xl:col-span-1">
                        <x-ui.field label="Assistance Type" name="assistance_id" required>
                            <x-ui.select name="assistance_id" placeholder="Select assistance" required x-model="assistanceId">
                                <option value="{{ $newValue }}">+ Create a new assistance item</option>
                                @if ($assistances->isNotEmpty())
                                    <optgroup label="Already in your catalogue">
                                        @foreach ($assistances as $assistance)
                                            <option value="{{ $assistance->id }}">
                                                {{ $assistance->name }} ({{ $assistance->type === 'cash' ? 'Cash' : 'In-Kind' }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Start Date" name="start_date" required>
                        <x-ui.input type="date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required />
                    </x-ui.field>

                    <x-ui.field label="Crop" name="crop_id" hint="Optional">
                        <x-ui.select name="crop_id" placeholder="Not crop specific" x-model="cropId"
                                     :options="$crops->pluck('name', 'id')" />
                    </x-ui.field>

                    <div class="sm:col-span-2 xl:col-span-3">
                        <x-ui.field :label="'Quantity / Amount Allocated'" name="allocated_quantity" hint="Bags, kilos, or a peso amount - whichever applies.">
                            <x-ui.input type="number" step="0.01" min="0" name="allocated_quantity"
                                        value="{{ old('allocated_quantity') }}" placeholder="0.00" />
                        </x-ui.field>
                    </div>

                    <div class="sm:col-span-2 xl:col-span-3">
                        <x-ui.field label="Remarks" name="remarks" hint="Optional">
                            <x-ui.textarea name="remarks" rows="2" placeholder="e.g. For distribution at the association center."
                                           maxlength="1000">{{ old('remarks') }}</x-ui.textarea>
                        </x-ui.field>
                    </div>
                </div>

                {{-- New assistance item, only while creating one --}}
                <div x-show="makingNew()" x-cloak x-transition class="mt-4 rounded-xl border border-primary/30 bg-accent p-4">
                    <p class="text-sm font-semibold text-accent-foreground">New assistance item</p>
                    <p class="mt-0.5 text-xs text-accent-foreground/80">
                        This is saved to your assistance catalogue as well, so next time you can just pick it.
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="sm:col-span-2">
                            <x-ui.field label="Name" name="new_assistance_name" required>
                                <x-ui.input name="new_assistance_name" x-model="newName" maxlength="255"
                                            placeholder="e.g. Certified Rice Seed" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Type" name="new_assistance_type" required>
                            <x-ui.select name="new_assistance_type" x-model="newType"
                                         :options="collect($types)->mapWithKeys(fn ($t) => [$t => $t === 'cash' ? 'Cash' : 'In-Kind'])" />
                        </x-ui.field>

                        <x-ui.field label="Total Available" name="new_assistance_available" hint="Optional">
                            <x-ui.input type="number" step="0.01" min="0" name="new_assistance_available"
                                        value="{{ old('new_assistance_available') }}" placeholder="0.00" />
                        </x-ui.field>

                        <div class="sm:col-span-2 xl:col-span-4">
                            <x-ui.field label="Description" name="new_assistance_description" hint="Optional">
                                <x-ui.input name="new_assistance_description" maxlength="1000"
                                            value="{{ old('new_assistance_description') }}" />
                            </x-ui.field>
                        </div>
                    </div>
                </div>
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
</div>
@endsection
