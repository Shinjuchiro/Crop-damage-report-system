@extends('layouts.app')

@section('title', 'Record a distribution')
@section('heading', 'Record a distribution')
@section('heading-fil', 'Itala ang Pamamahagi')
@section('subheading', ($allocation->assistance?->name ?? 'Assistance') . ' from ' . $association->name)

@section('header-actions')
    <x-ui.button variant="outline" :href="route('association.assistance.show', $allocation)">Cancel</x-ui.button>
@endsection

@section('content')

{{--
    Proposal sections 64, 65 and 91.9.

    Two rules shape this form, and both are enforced again in the controller
    because a dropdown is only a suggestion once the form has been posted:

      1. Only members with a damage report a technician has VERIFIED appear
         in the list. Aid is tied to inspected damage, not to who asked first.
      2. You cannot give out more than the office allocated. The remaining
         balance is worked out from what has already been recorded.

    Nothing saves until the confirmation dialog. The review panel at the
    bottom is the officer reading their own answers back first.
--}}

@php
    // Feed the whole eligible list into Alpine so choosing a member can
    // immediately narrow the report dropdown to that member's own reports.
    $memberOptions = $eligible->map(fn ($farmer) => [
        'id'       => $farmer->id,
        'name'     => $farmer->full_name,
        'barangay' => $farmer->barangay?->name ?? 'Barangay not set',
        'reports'  => $farmer->damageReports->map(fn ($report) => [
            'id'        => $report->id,
            'reference' => $report->reference,
            'label'     => $report->reference . ' - ' . $report->damage_cause_label
                            . ' (' . $report->created_at?->format('M d, Y') . ')',
            'crops'     => $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', '),
            'area'      => number_format($report->crops->sum('damaged_area_hectares'), 2),
            'assessed'  => $report->validation?->assessed_damage_percent !== null
                            ? round($report->validation->assessed_damage_percent) . '%'
                            : 'not recorded',
        ])->values(),
    ])->values();
@endphp

<div x-data="distributionForm(@js($memberOptions), @js($remaining))" class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-4">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- What is being handed out --}}
    <x-ui.card title="What you are giving out">
        <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assistance</dt>
                <dd class="mt-1 text-sm font-medium">
                    {{ $allocation->assistance?->name ?? $allocation->in_kind_description ?? 'Assistance' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">For disaster</dt>
                <dd class="mt-1 text-sm font-medium">{{ $allocation->disaster?->name ?? 'Not tied to one' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Remaining</dt>
                <dd class="mt-1 text-sm font-bold">
                    {{ $remaining === null ? 'No quantity tracked' : number_format($remaining, 2) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Eligible members</dt>
                <dd class="mt-1 text-sm font-medium">{{ $eligible->count() }}</dd>
            </div>
        </dl>
    </x-ui.card>

    @if ($eligible->isEmpty())
        <x-ui.card>
            <x-ui.empty title="No member is eligible yet"
                        icon="M12 22a10 10 0 100-20 10 10 0 000 20zM12 7.5v5M12 16.5h.01"
                        message="Assistance can only be recorded against a damage report that a technician has already inspected and verified. None of your members has one yet.">
                <x-ui.button variant="outline" :href="route('association.reports.index')">
                    See your members' reports
                </x-ui.button>
            </x-ui.empty>
        </x-ui.card>
    @else

        <form method="POST" action="{{ route('association.assistance.store', $allocation) }}"
              enctype="multipart/form-data"
              data-confirm="Please check the member, the report, the quantity and the date before recording this. Once recorded, the member will be asked to confirm they received it."
              data-confirm-title="Record this distribution?"
              data-confirm-action="Confirm &amp; Record"
              :data-confirm-review="reviewJson()">
            @csrf

            <div class="space-y-4">

                {{-- 1. WHO ---------------------------------------------- --}}
                <x-ui.card title="1. Who received it?" description="Sino ang nakatanggap?">
                    <div class="space-y-1.5">
                        <label for="farmer_id" class="block text-sm font-medium">
                            Member <span class="text-destructive">*</span>
                        </label>
                        <select id="farmer_id" name="farmer_id" x-model="farmerId" required
                                class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            <option value="">Select a member</option>
                            @foreach ($eligible as $farmer)
                                <option value="{{ $farmer->id }}" @selected(old('farmer_id') == $farmer->id)>
                                    {{ $farmer->full_name }} ({{ $farmer->barangay?->name ?? 'no barangay' }})
                                </option>
                            @endforeach
                        </select>

                        <p class="text-xs text-muted-foreground">
                            Only members with a verified damage report appear here. If somebody is missing, their
                            report has not been inspected yet.
                        </p>
                    </div>
                </x-ui.card>

                {{-- 2. WHICH REPORT ------------------------------------- --}}
                <x-ui.card title="2. For which damage report?"
                           description="Para sa aling ulat ng pinsala?">

                    <div x-show="! farmerId" x-cloak
                         class="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                        Choose a member first and their verified reports will appear here.
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

                        {{-- A small summary so the officer can see they picked
                             the right one without opening another page. --}}
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
                            Every distribution is tied to the report that made the member eligible, so the office
                            can always trace assistance back to inspected damage.
                        </p>
                    </div>
                </x-ui.card>

                {{-- 3. HOW MUCH ----------------------------------------- --}}
                <x-ui.card title="3. How much, and when?" description="Magkano at kailan?">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="quantity" class="block text-sm font-medium">
                                Quantity or amount <span class="text-destructive">*</span>
                            </label>
                            <input id="quantity" type="number" name="quantity" step="0.01" min="0.01" required
                                   x-model="quantity" inputmode="decimal" value="{{ old('quantity') }}"
                                   class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">

                            {{-- Checked here as well as on the server, so the
                                 officer finds out before they submit. --}}
                            <p x-show="overRemaining()" x-cloak
                               class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900
                                      dark:bg-amber-950/60 dark:text-amber-200">
                                That is more than the
                                <span x-text="Number(remaining).toFixed(2)"></span>
                                left on this allocation.
                            </p>

                            @if ($remaining !== null)
                                <p class="text-xs text-muted-foreground">
                                    {{ number_format($remaining, 2) }} remaining on this allocation.
                                </p>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <label for="distributed_at" class="block text-sm font-medium">
                                Date handed over <span class="text-destructive">*</span>
                            </label>
                            <input id="distributed_at" type="date" name="distributed_at" required
                                   x-model="distributedAt" max="{{ now()->toDateString() }}"
                                   class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            <p class="text-xs text-muted-foreground">
                                The day the member actually received it, which may not be today.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-1.5">
                        <label for="in_kind_description" class="block text-sm font-medium">
                            What exactly was given
                            <span class="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <input id="in_kind_description" type="text" name="in_kind_description" maxlength="255"
                               x-model="description"
                               value="{{ old('in_kind_description', $allocation->in_kind_description) }}"
                               placeholder="e.g. 5 bags of certified rice seed"
                               class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                    </div>

                    <div class="mt-5 space-y-1.5">
                        <label for="remarks" class="block text-sm font-medium">
                            Remarks <span class="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <textarea id="remarks" name="remarks" rows="3" maxlength="1000" x-model="remarks"
                                  placeholder="Anything the office should know about this handover"
                                  class="w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm">{{ old('remarks') }}</textarea>
                    </div>
                </x-ui.card>

                {{-- 4. PROOF -------------------------------------------- --}}
                <x-ui.card title="4. Proof of handover"
                           description="Larawan ng pamamahagi. Optional but strongly recommended.">

                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl
                                  border-2 border-dashed border-input px-4 py-8 text-center
                                  transition-colors hover:border-primary hover:bg-accent">
                        <svg class="h-9 w-9 text-muted-foreground" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            <circle cx="12" cy="13" r="3.5"/>
                        </svg>
                        <span class="text-sm font-semibold">Add photos of the handover</span>
                        <span class="text-xs text-muted-foreground">Hanggang 6 na larawan</span>

                        <input type="file" name="photos[]" multiple accept="image/*" capture="environment"
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
                        These are the association's own record of the handover. They are stored separately from
                        anything the member uploads.
                    </p>
                </x-ui.card>

                {{-- REVIEW ---------------------------------------------- --}}
                <x-ui.card title="Review before recording" description="I-review bago itala">
                    <dl class="divide-y divide-border rounded-xl border border-border">
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Member</dt>
                            <dd class="text-right text-sm font-medium" x-text="memberName()"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">For report</dt>
                            <dd class="text-right text-sm font-medium"
                                x-text="chosenReport()?.reference || 'Not chosen'"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Assistance</dt>
                            <dd class="text-right text-sm font-medium">
                                {{ $allocation->assistance?->name ?? 'Assistance' }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Quantity</dt>
                            <dd class="text-right text-sm font-semibold" x-text="quantity || 'Not entered'"></dd>
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
                                Ready to record. The member will be asked to confirm they received it.
                            </span>
                            <span x-show="! isComplete()" x-cloak x-text="whatIsMissing()"></span>
                        </p>
                    </div>
                </x-ui.card>

                <div class="flex flex-col-reverse gap-3 pb-4 sm:flex-row sm:justify-end">
                    <x-ui.button size="lg" variant="outline"
                                 :href="route('association.assistance.show', $allocation)">
                        Cancel
                    </x-ui.button>
                    <x-ui.button size="lg" type="submit" x-bind:disabled="! isComplete()">
                        Review &amp; Record Distribution
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
     * The distribution form's state.
     *
     * The only clever bit is reportsForMember(): choosing a member narrows the
     * report dropdown to that member's own verified reports, so it is not
     * possible to pick a member and then a report belonging to somebody else.
     * The controller checks that pairing again anyway.
     */
    function distributionForm(members, remaining) {
        return {
            members,
            remaining,

            farmerId: '',
            reportId: '',
            quantity: '',
            distributedAt: new Date().toISOString().slice(0, 10),   // today, in the value format a date input wants
            description: '',
            remarks: '',
            previews: [],

            /* ---------- member and report ---------- */

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

            /* ---------- quantity ---------- */

            overRemaining() {
                if (this.remaining === null || ! this.quantity) {
                    return false;
                }

                // The tiny allowance is for rounding, so giving the last 2.50
                // out of exactly 2.5 is not flagged.
                return parseFloat(this.quantity) > parseFloat(this.remaining) + 0.001;
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
                if (! this.farmerId)      return 'Choose which member received the assistance.';
                if (! this.reportId)      return 'Choose which damage report this is for.';
                if (! this.quantity)      return 'Enter how much was given.';
                if (this.overRemaining()) return 'The quantity is more than what is left on this allocation.';
                if (! this.distributedAt) return 'Choose the date it was handed over.';

                return '';
            },

            isComplete() {
                return this.whatIsMissing() === '';
            },

            /* ---------- the confirmation dialog (section 91.9) ---------- */

            reviewJson() {
                return JSON.stringify([
                    { label: 'Member',          value: this.memberName() },
                    { label: 'For report',      value: this.chosenReport()?.reference || 'Not chosen' },
                    { label: 'Assistance',      value: @js($allocation->assistance?->name ?? 'Assistance') },
                    { label: 'Quantity',        value: String(this.quantity || 'Not entered') },
                    { label: 'What was given',  value: this.description || 'Not specified' },
                    { label: 'Date handed over',value: this.distributedAt || 'Not chosen' },
                    { label: 'Photos attached', value: String(this.previews.length) },
                    { label: 'Remarks',         value: this.remarks || 'None' },
                ]);
            },
        };
    }
</script>
@endpush
