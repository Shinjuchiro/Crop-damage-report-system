@extends('layouts.app')

@section('title', 'Record a hand-over')
@section('heading', 'Record a hand-over')
@section('heading-fil', 'Itala ang Pamamahagi')
@section('subheading', $allocation->display_name . ' to a member of ' . ($allocation->concerned_associations->pluck('name')->join(', ') ?: ($allocation->association?->name ?? 'the designated farmers')))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('mao.assistance-allocations.show', $allocation)">Cancel</x-ui.button>
@endsection

@section('content')

{{--
    Proposal sections 64, 65 and 91.9.

    THE OFFICE RECORDS THIS NOW (Oct 2026, client decision)

    This form was the association's. The Municipal Agriculture Office hands
    assistance to the farmer and records it here instead, and the association
    monitors. Section 61 only ever gave the association monitoring; its
    Distribute screen was added later because without it nothing in the
    system could write an assistance_distributions row at all.

    WHAT IS NOT ON THIS FORM

    Quantity, amount, and "what exactly was given". All three were on the old
    version, and all three are gone with every other figure in this module:
    how much a named farmer received is a financial detail about an
    identifiable person, and the client asked for it out of the system. The
    allocation's own name says what this is.

    One rule shapes the rest, and it is enforced again in the controller
    because a dropdown is only a suggestion once the form has been posted:
    only farmers the office designated on THIS allocation appear in the list.

    Nothing saves until the confirmation dialog. The review panel near the
    bottom is the officer reading their own answers back first.
--}}

@php
    // The whole designated list goes into Alpine, so choosing a farmer can
    // immediately narrow the report dropdown to that farmer's own reports.
    $memberOptions = $eligible->map(fn ($farmer) => [
        'id'       => $farmer->id,
        'name'     => $farmer->full_name,
        'barangay' => $farmer->barangay?->name ?? 'Barangay not set',
        'reports'  => $farmer->damageReports->map(fn ($report) => [
            'id'        => $report->id,
            'reference' => $report->full_reference,
            'label'     => $report->full_reference . ' - ' . $report->damage_cause_label
                            . ' (' . $report->created_at?->format('M d, Y') . ')',
            'crops'     => $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', '),
            'area'      => number_format($report->crops->sum('damaged_area_hectares'), 2),
            'assessed'  => $report->validation?->assessed_damage_percent !== null
                            ? round($report->validation->assessed_damage_percent) . '%'
                            : 'not recorded',
        ])->values(),
    ])->values();
@endphp

<div x-data="handoverForm(@js($memberOptions))" class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-4">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- What is being handed over --}}
    <x-ui.card title="What you are handing over">
        <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assistance</dt>
                <dd class="mt-1 text-sm font-medium">{{ $allocation->display_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Association</dt>
                <dd class="mt-1 text-sm font-medium">{{ $allocation->concerned_associations->pluck('name')->join(', ') ?: ($allocation->association?->name ?? '-') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">For disaster</dt>
                <dd class="mt-1 text-sm font-medium">{{ $allocation->disaster?->name ?? 'Not tied to one' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Designated farmers</dt>
                <dd class="mt-1 text-sm font-medium">{{ $eligible->count() }}</dd>
            </div>
        </dl>
    </x-ui.card>

    @if ($eligible->isEmpty())
        <x-ui.card>
            <x-ui.empty title="Nobody is designated on this allocation yet"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 7.5v5M12 16.5h.01"
                        message="No farmer was designated when this allocation was created, so there is nobody to hand it to. Edit the allocation to designate the farmers it is for.">
                <x-ui.button variant="outline" :href="route('mao.assistance-allocations.index')">
                    Back to Assistance Allocation
                </x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else

        <form method="POST" action="{{ route('mao.assistance-allocations.distribute.store', $allocation) }}"
              enctype="multipart/form-data"
              data-confirm="Please check the farmer, the report and the date before recording this. Once recorded, the farmer will be asked to confirm they received it."
              data-confirm-title="Record this hand-over?"
              data-confirm-action="Confirm &amp; Record"
              :data-confirm-review="reviewJson()">
            @csrf

            <div class="space-y-4">

                {{-- 1. WHO ---------------------------------------------- --}}
                <x-ui.card title="1. Who received it?" description="Sino ang nakatanggap?">
                    <div class="space-y-1.5">
                        <label for="farmer_id" class="block text-sm font-medium">
                            Farmer <span class="text-destructive">*</span>
                        </label>
                        <select id="farmer_id" name="farmer_id" x-model="farmerId" required
                                class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            <option value="">Select a farmer</option>
                            @foreach ($eligible as $farmer)
                                <option value="{{ $farmer->id }}" @selected(old('farmer_id') == $farmer->id)>
                                    {{ $farmer->full_name }} ({{ $farmer->barangay?->name ?? 'no barangay' }})
                                </option>
                            @endforeach
                        </select>

                        <p class="text-xs text-muted-foreground">
                            Only farmers designated on this allocation appear here. If somebody is missing, they
                            were not designated when this allocation was created.
                        </p>
                    </div>
                </x-ui.card>

                {{-- 2. WHICH REPORT ------------------------------------- --}}
                <x-ui.card title="2. For which damage report?"
                           description="Para sa aling ulat ng pinsala?">

                    <div x-show="! farmerId" x-cloak
                         class="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                        Choose a farmer first and the report that made them a designated beneficiary will appear here.
                    </div>

                    <div x-show="farmerId" x-cloak class="space-y-1.5">
                        <label for="damage_report_id" class="block text-sm font-medium">
                            Damage report <span class="text-destructive">*</span>
                        </label>
                        <select id="damage_report_id" name="damage_report_id" x-model="reportId"
                                class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            <option value="">Select a report</option>
                            <template x-for="report in reportsForMember()" :key="report.id">
                                <option :value="report.id" x-text="report.label"></option>
                            </template>
                        </select>

                        {{-- A small summary, so the officer can see they
                             picked the right one without opening another
                             page. --}}
                        <div x-show="chosenReport()" x-cloak
                             class="mt-3 rounded-lg bg-muted px-4 py-3 text-sm">
                            <p><span class="text-muted-foreground">Crops:</span>
                               <span x-text="chosenReport()?.crops || 'not recorded'"></span></p>
                            <p class="mt-1"><span class="text-muted-foreground">Damaged area:</span>
                               <span x-text="(chosenReport()?.area || '0') + ' ha'"></span></p>
                            <p class="mt-1"><span class="text-muted-foreground">Technician assessed:</span>
                               <span class="font-medium" x-text="chosenReport()?.assessed"></span></p>
                        </div>

                        <p class="mt-2 text-xs text-muted-foreground">
                            Every hand-over is tied to the report that made the farmer eligible, so assistance can
                            always be traced back to inspected damage.
                        </p>
                    </div>
                </x-ui.card>

                {{-- 3. WHEN --------------------------------------------- --}}
                <x-ui.card title="3. When was it handed over?" description="Kailan ibinigay?">
                    <div class="space-y-1.5 sm:max-w-xs">
                        <label for="distributed_at" class="block text-sm font-medium">
                            Date handed over <span class="text-destructive">*</span>
                        </label>
                        <input id="distributed_at" type="date" name="distributed_at" required
                               x-model="distributedAt" max="{{ now()->toDateString() }}"
                               class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                        <p class="text-xs text-muted-foreground">
                            The day the farmer actually received it, which may not be today.
                        </p>
                    </div>

                    {{-- The quantity and "what exactly was given" fields were
                         here. Neither is recorded any more; see the note at
                         the top of this file. --}}

                    <div class="mt-5 space-y-1.5">
                        <label for="remarks" class="block text-sm font-medium">
                            Remarks <span class="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <textarea id="remarks" name="remarks" rows="3" maxlength="1000" x-model="remarks"
                                  placeholder="Anything worth recording about this hand-over"
                                  class="w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm">{{ old('remarks') }}</textarea>
                    </div>
                </x-ui.card>

                {{-- 4. PROOF -------------------------------------------- --}}
                <x-ui.card title="4. Proof of hand-over"
                           description="Larawan ng pamamahagi. Optional but strongly recommended.">

                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl
                                  border-2 border-dashed border-input px-4 py-8 text-center
                                  transition-colors hover:border-primary hover:bg-accent">
                        <svg class="h-9 w-9 text-muted-foreground" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            <circle cx="12" cy="13" r="3.5"/>
                        </svg>
                        <span class="text-sm font-semibold">Add photos of the hand-over</span>
                        <span class="text-xs text-muted-foreground">Hanggang 6 na larawan</span>

                        {{-- No capture attribute: it would send the phone
                             straight to the camera and remove the gallery and
                             file options this label's own wording promises. --}}
                        <input type="file" name="photos[]" multiple accept="image/*"
                               x-ref="photos" @change="onPhotos($event)" class="hidden">
                    </label>

                    <div x-show="previews.length" x-cloak class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
                        <template x-for="(preview, index) in previews" :key="preview.key">
                            <div class="relative aspect-square overflow-hidden rounded-lg border border-border">
                                <img :src="preview.url" alt="" class="h-full w-full object-cover">
                                <button type="button" @click="removePhoto(index)"
                                        class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center
                                               rounded-full bg-slate-900/70 text-white"
                                        :aria-label="'Remove photo ' + (index + 1)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                                         stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <p class="mt-3 text-xs text-muted-foreground">
                        These are the office's own record of the hand-over. They are stored separately from
                        anything the farmer uploads.
                    </p>
                </x-ui.card>

                {{-- REVIEW ---------------------------------------------- --}}
                <x-ui.card title="Review before recording" description="I-review bago itala">
                    <dl class="divide-y divide-border rounded-xl border border-border">
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Farmer</dt>
                            <dd class="text-right text-sm font-medium" x-text="memberName()"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">For report</dt>
                            <dd class="text-right text-sm font-medium"
                                x-text="chosenReport()?.reference || 'Not chosen'"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Assistance</dt>
                            <dd class="text-right text-sm font-medium">{{ $allocation->display_name }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Date handed over</dt>
                            <dd class="text-right text-sm font-medium" x-text="distributedAt || 'Not chosen'"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Photos</dt>
                            <dd class="text-right text-sm font-medium" x-text="previews.length"></dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-start gap-3 rounded-xl px-4 py-3"
                         :class="isComplete()
                            ? 'border border-primary/30 bg-accent'
                            : 'bg-amber-50 dark:bg-amber-950/60'">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                             :class="isComplete() ? 'text-primary' : 'text-amber-600 dark:text-amber-400'">
                            <circle cx="12" cy="12" r="10"/>
                            <path x-show="isComplete()" d="M8.5 12.2l2.4 2.4 4.6-4.8"/>
                            <path x-show="! isComplete()" d="M12 7.5v5M12 16.5h.01"/>
                        </svg>
                        <p class="text-sm"
                           :class="isComplete() ? 'text-accent-foreground' : 'text-amber-900 dark:text-amber-200'">
                            <span x-show="isComplete()">
                                Ready to record. The farmer will be asked to confirm they received it.
                            </span>
                            <span x-show="! isComplete()" x-cloak x-text="whatIsMissing()"></span>
                        </p>
                    </div>
                </x-ui.card>

                <div class="flex flex-col-reverse gap-3 pb-4 sm:flex-row sm:justify-end">
                    <x-ui.button size="lg" variant="outline"
                                 :href="route('mao.assistance-allocations.show', $allocation)">
                        Cancel
                    </x-ui.button>
                    <x-ui.button size="lg" type="submit" x-bind:disabled="! isComplete()">
                        Review &amp; Record Hand-over
                    </x-ui.button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    /*
     * The hand-over form's state.
     *
     * The only clever bit is reportsForMember(): choosing a farmer narrows
     * the report dropdown to the report(s) that made THEM a designated
     * beneficiary of THIS allocation, so it is not possible to pick a farmer
     * and then a report belonging to somebody else. The controller checks
     * that pairing, and the designation itself, again anyway.
     *
     * The quantity half of the old association version is gone: no quantity
     * field, no remaining balance, and no overRemaining() check, because
     * there is no figure on an allocation to be over.
     */
    function handoverForm(members) {
        return {
            members,

            farmerId: '',
            reportId: '',
            distributedAt: new Date().toISOString().slice(0, 10),   // today, in the format a date input wants
            remarks: '',
            previews: [],

            /* ---------- farmer and report ---------- */

            member() {
                return this.members.find(m => String(m.id) === String(this.farmerId));
            },

            memberName() {
                return this.member()?.name || 'Not chosen';
            },

            reportsForMember() {
                return this.member()?.reports || [];
            },

            chosenReport() {
                return this.reportsForMember().find(r => String(r.id) === String(this.reportId)) || null;
            },

            /* ---------- photos ---------- */

            onPhotos(event) {
                this.previews.forEach(p => URL.revokeObjectURL(p.url));

                this.previews = Array.from(event.target.files).map(file => ({
                    key: file.name + file.size + file.lastModified,
                    url: URL.createObjectURL(file),
                }));
            },

            removePhoto(index) {
                const input = this.$refs.photos;
                const kept = new DataTransfer();

                Array.from(input.files).forEach((file, i) => {
                    if (i !== index) kept.items.add(file);
                });

                input.files = kept.files;
                URL.revokeObjectURL(this.previews[index].url);
                this.previews.splice(index, 1);
            },

            /* ---------- is it ready ---------- */

            whatIsMissing() {
                if (! this.farmerId)      return 'Choose which farmer received the assistance.';
                if (! this.reportId)      return 'Choose which damage report this is for.';
                if (! this.distributedAt) return 'Choose the date it was handed over.';

                return '';
            },

            isComplete() {
                return this.whatIsMissing() === '';
            },

            /* ---------- the confirmation dialog (section 91.9) ---------- */

            reviewJson() {
                return JSON.stringify([
                    { label: 'Farmer',           value: this.memberName() },
                    { label: 'For report',       value: this.chosenReport()?.reference || 'Not chosen' },
                    { label: 'Assistance',       value: @js($allocation->display_name) },
                    { label: 'Date handed over', value: this.distributedAt || 'Not chosen' },
                    { label: 'Photos attached',  value: String(this.previews.length) },
                    { label: 'Remarks',          value: this.remarks || 'None' },
                ]);
            },
        };
    }
</script>
@endpush
