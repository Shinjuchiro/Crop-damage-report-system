@extends('layouts.app')

@section('title', 'Record Crop Planting')
@section('heading', 'Record Crop Planting')
@section('subheading', 'What did you plant, when, and how much land did it take? Ano ang itinanim, kailan, at gaano kalaki?')

@section('content')

@php
    /*
     | What the farmer typed last time, when validation sent them back.
     |
     | Without this every crop row resets to blank, which for an elderly
     | farmer who has just entered three crops with their dates and areas is
     | the difference between correcting one field and starting over.
     */
    $oldRows = collect(old('crops', []))
        ->filter(fn ($crop) => filled($crop['crop_id'] ?? null))
        ->map(fn ($crop) => [
            'key'           => uniqid('r', true),
            'crop_id'       => (string) ($crop['crop_id'] ?? ''),
            'crop_specify'  => $crop['crop_specify'] ?? '',
            'date_planted'  => $crop['date_planted'] ?? '',
            'area_hectares' => $crop['area_hectares'] ?? '',
        ])
        ->values();
@endphp

{{--
    Proposal sections 24 to 27.

    The form opens with Crop 1 required and Crop 2 optional, exactly as the
    prototype shows, and "Add another crop" keeps going from there. The
    database has no crop_1 or crop_2 columns: each row here becomes its own
    record in crop_planting_record_crops (section 79).

    Submitting does NOT save. It opens the review dialog first, built from the
    rows below, because most of the people filling this in are elderly and a
    mistyped hectare figure follows a farmer through every report afterwards.
--}}

<div
     x-data="plantingForm(@js($crops->map(fn ($crop) => [
         'id'   => $crop->id,
         'name' => $crop->name,
         'hvcc' => (bool) $crop->is_hvcc,
     ])->values()))">

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('farmer.planting.store') }}"
          data-confirm="Please check the crop, the planting date and the area before saving. Pakisuri po muna bago i-save."
          data-confirm-title="Confirm crop planting activity?"
          data-confirm-action="Confirm &amp; Submit"
          :data-confirm-review="reviewJson()">
        @csrf

        <x-ui.card title="Crops planted" description="Add one block for every crop you planted.">

            <div class="space-y-5">

                <template x-for="(row, index) in rows" :key="row.key">
                    <div class="rounded-xl border border-border bg-muted/40 p-4 sm:p-5">

                        <div class="mb-4 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                <span x-text="'Crop ' + (index + 1)"></span>
                                <span x-show="index === 0" class="text-destructive">*</span>
                                <span x-show="index > 0" class="font-normal normal-case tracking-normal">
                                    (optional)
                                </span>
                            </p>

                            <button type="button" x-show="rows.length > 1" @click="removeRow(index)"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs
                                           font-medium text-destructive hover:bg-destructive/10">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 6h18M8 6V4h8v2m-9 0v14a1 1 0 001 1h8a1 1 0 001-1V6"/>
                                </svg>
                                Remove
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

                            {{-- Crop type --}}
                            <div class="space-y-1.5 sm:col-span-2 xl:col-span-3">
                                <label class="block text-sm font-medium">
                                    Crop Type <span class="text-destructive">*</span>
                                    <span class="block text-xs font-normal text-muted-foreground">Uri ng pananim</span>
                                </label>

                                <div class="relative">
                                    <select :name="`crops[${index}][crop_id]`" x-model="row.crop_id" required
                                            class="block h-12 w-full appearance-none rounded-md border border-input
                                                   bg-card px-3 pr-9 text-base shadow-sm">
                                        <option value="">Select a crop</option>
                                        <template x-for="crop in crops" :key="crop.id">
                                            <option :value="crop.id" x-text="crop.name"></option>
                                        </template>
                                    </select>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                                </div>
                            </div>

                            {{-- Only asked when the selected crop is a High Value Commercial Crop --}}
                            <div class="space-y-1.5 sm:col-span-2 xl:col-span-3" x-show="isHvcc(row)" x-cloak>
                                <label class="block text-sm font-medium">
                                    Which high value crop? <span class="text-destructive">*</span>
                                    <span class="block text-xs font-normal text-muted-foreground">
                                        Halimbawa: Ampalaya, Talong, Mangga
                                    </span>
                                </label>
                                <input type="text" :name="`crops[${index}][crop_specify]`"
                                       x-model="row.crop_specify" maxlength="100"
                                       placeholder="e.g. Ampalaya"
                                       class="block h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            </div>

                            {{-- Date planted --}}
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">
                                    Date Planted <span class="text-destructive">*</span>
                                    <span class="block text-xs font-normal text-muted-foreground">Petsa ng pagtatanim</span>
                                </label>
                                <input type="date" :name="`crops[${index}][date_planted]`"
                                       x-model="row.date_planted" required max="{{ now()->toDateString() }}"
                                       class="block h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            </div>

                            {{-- Area --}}
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">
                                    Area Planted <span class="text-destructive">*</span>
                                    <span class="block text-xs font-normal text-muted-foreground">Laki ng taniman (hektarya)</span>
                                </label>
                                <div class="relative">
                                    <input type="number" :name="`crops[${index}][area_hectares]`"
                                           x-model="row.area_hectares" required step="0.01" min="0.01"
                                           inputmode="decimal" placeholder="0.00"
                                           class="block h-12 w-full rounded-md border border-input bg-card px-3 pr-12
                                                  text-base shadow-sm">
                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2
                                                 text-sm text-muted-foreground">ha</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addRow()"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed
                               border-input px-4 py-4 text-sm font-medium text-muted-foreground
                               transition-colors hover:border-primary hover:bg-accent hover:text-accent-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Add another crop &middot; Magdagdag pa
                </button>

                <div class="flex items-center justify-between rounded-xl bg-accent px-4 py-3
                            text-sm font-medium text-accent-foreground">
                    <span>Total area planted &middot; Kabuuang laki</span>
                    <span x-text="totalArea().toFixed(2) + ' ha'"></span>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button size="lg" variant="outline" :href="route('farmer.planting.index')">
                        Cancel
                    </x-ui.button>
                    <x-ui.button size="lg" type="submit">Review &amp; Submit</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function plantingForm(crops) {
        return {
            crops,

            // Opens with Crop 1 required and Crop 2 optional, matching the
            // prototype. Neither the form nor the database stops at two.
            // On a failed submission it reopens with whatever was typed,
            // rather than blank: see the $oldRows note at the top of the file.
            rows: @js($oldRows).length ? @js($oldRows) : [emptyRow(), emptyRow()],

            addRow() {
                this.rows.push(emptyRow());
            },

            removeRow(index) {
                this.rows.splice(index, 1);
            },

            cropName(row) {
                const crop = this.crops.find(c => String(c.id) === String(row.crop_id));

                return crop ? crop.name : null;
            },

            isHvcc(row) {
                const crop = this.crops.find(c => String(c.id) === String(row.crop_id));

                return crop ? crop.hvcc : false;
            },

            totalArea() {
                return this.rows.reduce((sum, row) => sum + (parseFloat(row.area_hectares) || 0), 0);
            },

            // Feeds the confirmation dialog. Built here rather than letting the
            // dialog read the fields itself, because repeated rows need to be
            // labelled "Crop 1", "Crop 2" to be readable.
            reviewJson() {
                const rows = [];

                this.rows.forEach((row, index) => {
                    const name = this.cropName(row);

                    if (! name) {
                        return;
                    }

                    const label = 'Crop ' + (index + 1);
                    const crop = row.crop_specify ? name + ' (' + row.crop_specify + ')' : name;

                    rows.push({ label: label, value: crop });
                    rows.push({ label: label + ' planted on', value: row.date_planted || 'Not set' });
                    rows.push({ label: label + ' area', value: (parseFloat(row.area_hectares) || 0).toFixed(2) + ' ha' });
                });

                rows.push({ label: 'Total area', value: this.totalArea().toFixed(2) + ' ha' });

                return JSON.stringify(rows);
            },
        };
    }

    function emptyRow() {
        return {
            key: Math.random().toString(36).slice(2),
            crop_id: '',
            crop_specify: '',
            date_planted: '',
            area_hectares: '',
        };
    }
</script>
@endpush
