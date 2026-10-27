@extends('layouts.app')

@section('title', 'Create Assistance Distribution')
@section('heading', 'Create Assistance Distribution')
@section('subheading', 'Create a new distribution batch when assistance becomes available. Select eligible farmers based on MAO-approved crop damage reports and add the necessary details.')

{{--
    CREATE ASSISTANCE DISTRIBUTION (Oct 2026 revision)

    Replaces the Allocate Assistance pop-up. The office creates a batch
    whenever assistance arrives, so this page is always available: there is
    no budget, amount or formula anywhere on it. Assistance details are one
    free-text field.

    Flow: fill in sections 1 and 2 -> Review & Confirm -> Confirm & Finalize
    (a confirmation dialog, per the global confirmation rule). Save Draft
    keeps an unfinished form without notifying anybody.

    All eligible farmers are rendered with the page and searched, filtered
    and paged in the browser, so ticking a farmer on page 1 survives moving to
    page 2. The server re-checks eligibility when the batch is finalized.
--}}

@php
    $payload = [
        'farmers'  => $farmers,
        'draftId'  => $draft?->id,
        'name'     => old('batch_name', $draft?->batch_name ?? ''),
        'details'  => old('details', $draft?->details ?? ''),
        'date'     => old('distribution_date', $draft?->distribution_date?->toDateString() ?? ''),
        'venue'    => old('venue', $draft?->venue ?? ''),
        'selected' => array_map('intval', old('beneficiary_ids', $draft?->farmer_ids ?? [])),
    ];
@endphp

@section('content')
{{-- A solid green button rather than the small text link, which was easy
     to miss. Same destination as before. --}}
<a href="{{ route('mao.assistance-allocations.index') }}"
   class="mb-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-ring">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
         stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    Back to Assistance Allocation
</a>

@if ($errors->any())
    <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </x-ui.alert>
@endif

@if (session('status'))
    <x-ui.alert class="mb-5">{{ session('status') }}</x-ui.alert>
@endif

{{-- Saved drafts --}}
@if ($drafts->isNotEmpty())
    <div class="mb-5 rounded-xl border border-border bg-card p-4 shadow-sm">
        <p class="mb-2 text-sm font-semibold text-foreground">Saved drafts</p>
        <ul class="divide-y divide-border">
            @foreach ($drafts as $d)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-foreground">{{ $d->batch_name ?: 'Untitled batch' }}
                            @if ($draft && $draft->id === $d->id)
                                <span class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary">Open now</span>
                            @endif
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ count($d->farmer_ids ?? []) }} {{ count($d->farmer_ids ?? []) === 1 ? 'farmer' : 'farmers' }}
                            &middot; saved {{ $d->updated_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button :href="route('mao.assistance-allocations.create', ['draft' => $d->id])"
                                     variant="outline" size="sm">Continue</x-ui.button>
                        <form method="POST" action="{{ route('mao.assistance-allocations.draft.destroy', $d) }}"
                              data-confirm="This saved draft will be permanently removed. Nothing has been sent to anyone."
                              data-confirm-title="Delete this draft?"
                              data-confirm-action="Delete Draft">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="destructive" size="sm">Delete</x-ui.button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div x-data="batchWizard(@js($payload))" x-cloak>

    {{-- ============ STEPPER ============ --}}
    <ol class="mb-5 flex items-center gap-2 overflow-x-auto rounded-xl border border-border bg-card px-4 py-4 shadow-sm sm:gap-4 sm:px-6">
        <template x-for="(label, i) in ['Basic Information', 'Select Beneficiaries', 'Review & Confirm', 'Finalize']" :key="i">
            <li class="flex min-w-0 flex-1 items-center gap-2 whitespace-nowrap">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold"
                      :class="stage > i + 1 ? 'border-primary bg-primary text-primary-foreground'
                            : stage === i + 1 ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-card text-muted-foreground'"
                      x-text="stage > i + 1 ? '✓' : i + 1"></span>
                <span class="hidden text-sm sm:inline"
                      :class="stage >= i + 1 ? 'font-semibold text-primary' : 'text-muted-foreground'" x-text="label"></span>
                <span x-show="i < 3" class="hidden h-px flex-1 bg-border sm:block"></span>
            </li>
        </template>
    </ol>

    <form id="batch-form" method="POST" action="{{ route('mao.assistance-allocations.store') }}" @submit.prevent>
        @csrf
        <input type="hidden" name="draft_id" :value="draftId">

        {{-- Everything that gets posted stays in the DOM on both screens. --}}
        <div class="hidden">
            <template x-for="id in selected" :key="id"><input type="hidden" name="beneficiary_ids[]" :value="id"></template>
        </div>

        {{-- ================= SCREEN 1: SECTIONS 1 AND 2 ================= --}}
        <div x-show="screen === 'form'" class="space-y-5">

            {{-- 1. Basic Information --}}
            <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <h2 class="mb-4 text-lg font-semibold text-foreground">1. Basic Information</h2>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="batch_name" class="mb-1 block text-sm font-semibold text-foreground">
                            Distribution Batch Name <span class="text-destructive">*</span>
                        </label>
                        <input id="batch_name" name="batch_name" type="text" maxlength="150" x-model="name"
                               placeholder="e.g., Typhoon Relief Assistance - Batch 001"
                               class="w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                        <p x-show="tried && ! name.trim()" class="mt-1 text-xs text-destructive">Please give this batch a name.</p>
                    </div>

                    <div class="md:row-span-2">
                        <label for="details" class="mb-1 block text-sm font-semibold text-foreground">
                            Assistance Details <span class="text-destructive">*</span>
                        </label>
                        <textarea id="details" name="details" rows="5" maxlength="500" x-model="details"
                                  placeholder="Enter assistance details when available (e.g., type of assistance, description, or relevant instructions)."
                                  class="w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none"></textarea>
                        <div class="flex justify-between">
                            <p x-show="tried && ! details.trim()" class="text-xs text-destructive">Please enter the assistance details.</p>
                            <p class="ml-auto text-xs text-muted-foreground"><span x-text="details.length"></span>/500</p>
                        </div>
                    </div>

                    <div>
                        <label for="distribution_date" class="mb-0.5 block text-sm font-semibold text-foreground">Distribution Date</label>
                        <p class="mb-1 text-xs text-muted-foreground">Optional until a schedule is confirmed.</p>
                        <div class="flex gap-2">
                            <input id="distribution_date" name="distribution_date" type="date" x-model="date"
                                   class="w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm focus:border-primary focus:outline-none">
                            <button type="button" x-show="date" @click="date = ''"
                                    class="rounded-lg border border-input px-3 text-sm text-muted-foreground hover:bg-muted/60"
                                    aria-label="Clear date">&times;</button>
                        </div>
                    </div>

                    <div>
                        <label for="venue" class="mb-0.5 block text-sm font-semibold text-foreground">Distribution Venue</label>
                        <p class="mb-1 text-xs text-muted-foreground">Optional until a schedule is confirmed.</p>
                        <input id="venue" name="venue" type="text" maxlength="255" x-model="venue"
                               placeholder="e.g., MAO Office, Tanza, Cavite"
                               class="w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none">
                    </div>
                </div>
            </section>

            {{-- 2. Select Beneficiaries --}}
            <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-foreground">2. Select Beneficiaries</h2>
                <p class="mb-4 mt-1 text-sm text-primary">
                    Choose eligible farmers with MAO-approved crop damage reports. You can filter by association and severity, and use bulk selection.
                </p>

                {{-- Filters --}}
                <div class="mb-4 grid gap-3 rounded-xl bg-muted/50 p-3 md:grid-cols-[1fr_auto_auto]">
                    <input type="search" x-model.debounce.200ms="search" @input="page = 1"
                           placeholder="Search by name or farmer ID..." aria-label="Search farmers"
                           class="w-full rounded-lg border border-input bg-card px-3 py-2.5 text-sm focus:border-primary focus:outline-none">

                    <select x-model="assoc" @change="page = 1" aria-label="Filter by association"
                            class="rounded-lg border border-input bg-card px-3 py-2.5 text-sm focus:border-primary focus:outline-none">
                        <option value="">All Associations</option>
                        @foreach ($associations as $association)
                            <option value="{{ $association->id }}">{{ $association->name }}</option>
                        @endforeach
                    </select>

                    <select x-model="sev" @change="page = 1" aria-label="Filter by damage severity"
                            class="rounded-lg border border-input bg-card px-3 py-2.5 text-sm focus:border-primary focus:outline-none">
                        <option value="">All Damage Severity</option>
                        <option value="slight">Slight Damage</option>
                        <option value="moderate">Moderate Damage</option>
                        <option value="total">Total Damage</option>
                    </select>
                </div>

                {{-- Selection bar --}}
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <p class="font-medium text-foreground"><span x-text="selected.length"></span>
                        <span x-text="selected.length === 1 ? 'farmer selected' : 'farmers selected'"></span></p>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="bulkSelect()"
                                class="rounded-lg border border-primary px-3 py-1.5 text-sm font-medium text-primary hover:bg-primary/5">
                            Bulk Select <span class="text-xs text-muted-foreground" x-show="filtered().length">(all <span x-text="selectableCount()"></span> shown by filters)</span>
                        </button>
                        <button type="button" @click="selected = []" class="text-sm font-medium text-primary hover:underline">Clear Selection</button>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-muted/60 text-left text-muted-foreground">
                            <tr>
                                <th class="w-10 px-3 py-3">
                                    <input type="checkbox" :checked="pageAllSelected()" @change="togglePage()"
                                           aria-label="Select all farmers on this page" class="h-4 w-4 rounded border-input accent-primary">
                                </th>
                                <th class="px-3 py-3 font-semibold">Farmer Name</th>
                                <th class="px-3 py-3 font-semibold">Farmer ID</th>
                                <th class="px-3 py-3 font-semibold">Association</th>
                                <th class="px-3 py-3 font-semibold">Damage Severity</th>
                                <th class="px-3 py-3 font-semibold">Verified Report</th>
                                <th class="px-3 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="f in pageRows()" :key="f.farmer_id">
                                <tr :class="f.designated ? 'bg-muted/30 text-muted-foreground' : 'hover:bg-muted/30'">
                                    <td class="px-3 py-3">
                                        <input type="checkbox" :checked="selected.includes(f.farmer_id)" :disabled="f.designated"
                                               @change="toggle(f.farmer_id)" :aria-label="'Select ' + f.name"
                                               class="h-4 w-4 rounded border-input accent-primary disabled:opacity-40">
                                    </td>
                                    <td class="px-3 py-3 font-medium text-foreground" x-text="f.name"></td>
                                    <td class="px-3 py-3" x-text="f.code"></td>
                                    <td class="px-3 py-3" x-text="f.association"></td>
                                    <td class="px-3 py-3">
                                        <template x-if="f.severity_label">
                                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium"
                                                  :style="`background:${f.severity_color}22;color:${f.severity_color}`"
                                                  x-text="f.severity_label + ' Damage' + (f.assessed !== null ? ' (' + Math.round(f.assessed) + '%)' : '')"></span>
                                        </template>
                                        <span x-show="! f.severity_label" class="text-xs">Not assessed</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                                            Verified
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span x-show="! f.designated" class="inline-flex rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Eligible</span>
                                        <span x-show="f.designated" class="inline-flex flex-col text-xs">
                                            <span class="font-medium text-amber-700">Already designated</span>
                                            <span class="text-muted-foreground" x-text="f.designated_batch"></span>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filtered().length === 0">
                                <td colspan="7" class="px-3 py-10 text-center text-muted-foreground">
                                    No eligible farmers match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <p>Showing <span x-text="pageRows().length"></span> of <span x-text="filtered().length.toLocaleString()"></span> eligible farmers</p>
                    <div class="flex items-center gap-1" x-show="lastPage() > 1">
                        <button type="button" @click="go(page - 1)" :disabled="page === 1" aria-label="Previous page"
                                class="h-8 w-8 rounded-lg border border-input hover:bg-muted/60 disabled:opacity-40">&lsaquo;</button>
                        <template x-for="(n, i) in pageList()" :key="i">
                            <button type="button" @click="typeof n === 'number' && go(n)" :disabled="typeof n !== 'number'"
                                    class="h-8 min-w-8 rounded-lg border px-2"
                                    :class="n === page ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:bg-muted/60'"
                                    x-text="n"></button>
                        </template>
                        <button type="button" @click="go(page + 1)" :disabled="page === lastPage()" aria-label="Next page"
                                class="h-8 w-8 rounded-lg border border-input hover:bg-muted/60 disabled:opacity-40">&rsaquo;</button>
                    </div>
                </div>
            </section>

            <p x-show="noneSelected && selected.length === 0" class="text-sm font-medium text-destructive">
                Please select at least one farmer before continuing.
            </p>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                <button type="button"
                        @click="$el.form.action = '{{ route('mao.assistance-allocations.draft.save') }}'; $el.form.submit()"
                        class="rounded-lg border border-primary px-5 py-2.5 text-sm font-semibold text-primary hover:bg-primary/5">
                    Save Draft
                </button>
                <button type="button" @click="toReview()"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:brightness-110">
                    Next: Review &amp; Confirm &rarr;
                </button>
            </div>
        </div>

        {{-- ================= SCREEN 2: REVIEW ================= --}}
        <div x-show="screen === 'review'" x-cloak class="space-y-5">
            <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <h2 class="mb-4 text-lg font-semibold text-foreground">3. Review &amp; Confirm</h2>

                <dl class="grid gap-x-8 gap-y-3 text-sm md:grid-cols-2">
                    <div><dt class="text-muted-foreground">Batch name</dt><dd class="font-semibold text-foreground" x-text="name"></dd></div>
                    <div><dt class="text-muted-foreground">Farmers selected</dt><dd class="font-semibold text-foreground" x-text="selected.length"></dd></div>
                    <div class="md:col-span-2"><dt class="text-muted-foreground">Assistance details</dt><dd class="whitespace-pre-line font-medium text-foreground" x-text="details"></dd></div>
                    <div><dt class="text-muted-foreground">Distribution date</dt>
                        <dd class="font-medium text-foreground" x-text="date ? new Date(date + 'T00:00:00').toLocaleDateString('en-PH', {year:'numeric', month:'long', day:'numeric'}) : 'To be scheduled'"></dd></div>
                    <div><dt class="text-muted-foreground">Venue</dt><dd class="font-medium text-foreground" x-text="venue || 'To be scheduled'"></dd></div>
                </dl>

                <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Selected farmers by association</p>
                <ul class="mb-4 flex flex-wrap gap-2 text-xs">
                    <template x-for="g in byAssociation()" :key="g.name">
                        <li class="rounded-full bg-muted px-3 py-1"><span class="font-medium text-foreground" x-text="g.name"></span>
                            <span class="text-muted-foreground" x-text="'(' + g.count + ')'"></span></li>
                    </template>
                </ul>

                <div class="max-h-72 overflow-y-auto rounded-xl border border-border">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-muted/60 text-left text-muted-foreground">
                            <tr><th class="px-3 py-2 font-semibold">Farmer</th><th class="px-3 py-2 font-semibold">Farmer ID</th>
                                <th class="px-3 py-2 font-semibold">Association</th><th class="px-3 py-2 font-semibold">Report</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="f in selectedFarmers()" :key="f.farmer_id">
                                <tr><td class="px-3 py-2 font-medium text-foreground" x-text="f.name"></td>
                                    <td class="px-3 py-2" x-text="f.code"></td>
                                    <td class="px-3 py-2" x-text="f.association"></td>
                                    <td class="px-3 py-2" x-text="f.reference"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p class="mt-4 text-sm text-muted-foreground">
                    No amounts or budget are recorded on a batch. The selected farmers are notified when you
                    finalize it.
                </p>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                <button type="button" @click="screen = 'form'"
                        class="rounded-lg border border-input px-5 py-2.5 text-sm font-semibold text-foreground hover:bg-muted/60">&larr; Back to Edit</button>
                <div class="flex flex-wrap gap-3">
                    <button type="button"
                            @click="$el.form.action = '{{ route('mao.assistance-allocations.draft.save') }}'; $el.form.submit()"
                            class="rounded-lg border border-primary px-5 py-2.5 text-sm font-semibold text-primary hover:bg-primary/5">Save Draft</button>
                    <button type="button" @click="confirming = true"
                            class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:brightness-110">
                        Confirm &amp; Finalize Batch
                    </button>
                </div>
            </div>
        </div>

        {{-- ================= FINAL CONFIRMATION ================= --}}
        <div x-show="confirming" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="confirming = false">
            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl" @click.outside="confirming = false">
                <h3 class="mb-1 text-lg font-semibold text-foreground">Finalize this distribution batch?</h3>
                <p class="mb-5 text-sm text-muted-foreground">
                    <strong x-text="name"></strong> will be recorded for
                    <strong x-text="selected.length"></strong>
                    <span x-text="selected.length === 1 ? 'farmer' : 'farmers'"></span>, and they will be notified right away.
                </p>
                <div class="flex gap-3">
                    <button type="button" @click="confirming = false"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">Back</button>
                    <button type="button" @click="submitting = true; $el.form.action = '{{ route('mao.assistance-allocations.store') }}'; $el.form.submit()"
                            :disabled="submitting"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:brightness-110 disabled:opacity-60">
                        <span x-text="submitting ? 'Finalizing...' : 'Confirm & Finalize'"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function batchWizard(cfg) {
        return {
            farmers: cfg.farmers,
            draftId: cfg.draftId,
            name: cfg.name, details: cfg.details, date: cfg.date, venue: cfg.venue,
            // Only keep ticked ids that are still eligible (a draft may be old).
            selected: cfg.selected.filter(id => cfg.farmers.some(f => f.farmer_id === id && ! f.designated)),
            search: '', assoc: '', sev: '',
            page: 1, perPage: 10,
            screen: 'form', tried: false, noneSelected: false, confirming: false, submitting: false,

            get stage() {
                if (this.confirming) return 4;
                if (this.screen === 'review') return 3;
                return this.basicOk() ? 2 : 1;
            },
            basicOk() { return this.name.trim() !== '' && this.details.trim() !== ''; },

            filtered() {
                const t = this.search.trim().toLowerCase();
                return this.farmers.filter(f =>
                    (! t || f.name.toLowerCase().includes(t) || f.code.toLowerCase().includes(t) || f.reference.toLowerCase().includes(t))
                    && (! this.assoc || String(f.association_id) === this.assoc)
                    && (! this.sev || f.severity_group === this.sev));
            },
            lastPage() { return Math.max(1, Math.ceil(this.filtered().length / this.perPage)); },
            pageRows() {
                const p = Math.min(this.page, this.lastPage());
                return this.filtered().slice((p - 1) * this.perPage, p * this.perPage);
            },
            go(n) { this.page = Math.min(Math.max(1, n), this.lastPage()); },
            pageList() {
                const last = this.lastPage(), cur = this.page, out = [];
                if (last <= 7) { for (let i = 1; i <= last; i++) out.push(i); return out; }
                out.push(1);
                if (cur > 3) out.push('…');
                for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) out.push(i);
                if (cur < last - 2) out.push('…');
                out.push(last);
                return out;
            },

            toggle(id) {
                this.selected = this.selected.includes(id) ? this.selected.filter(x => x !== id) : [...this.selected, id];
            },
            selectableOnPage() { return this.pageRows().filter(f => ! f.designated).map(f => f.farmer_id); },
            pageAllSelected() {
                const ids = this.selectableOnPage();
                return ids.length > 0 && ids.every(id => this.selected.includes(id));
            },
            togglePage() {
                const ids = this.selectableOnPage();
                this.selected = this.pageAllSelected()
                    ? this.selected.filter(id => ! ids.includes(id))
                    : [...new Set([...this.selected, ...ids])];
            },
            selectableCount() { return this.filtered().filter(f => ! f.designated).length; },
            // Everything the current filters show, across all pages.
            bulkSelect() {
                const ids = this.filtered().filter(f => ! f.designated).map(f => f.farmer_id);
                this.selected = [...new Set([...this.selected, ...ids])];
            },

            selectedFarmers() { return this.farmers.filter(f => this.selected.includes(f.farmer_id)); },
            byAssociation() {
                const m = {};
                this.selectedFarmers().forEach(f => { m[f.association] = (m[f.association] || 0) + 1; });
                return Object.entries(m).map(([name, count]) => ({ name, count })).sort((a, b) => a.name.localeCompare(b.name));
            },

            toReview() {
                this.tried = true;
                if (! this.basicOk()) { document.getElementById('batch_name').scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
                this.noneSelected = this.selected.length === 0;
                if (this.noneSelected) { return; }
                this.screen = 'review';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        };
    }
</script>
@endpush
