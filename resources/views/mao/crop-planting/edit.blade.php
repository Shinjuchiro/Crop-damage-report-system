@extends('layouts.app')

@section('title', 'Edit Planting Record')
@section('heading', 'Edit Planting Record')
@section('subheading', "Correct a farmer's mistake - wrong crop, date or area.")

@section('header-actions')
    <x-ui.button variant="outline" :href="route('mao.crop-planting.show', $plantingRecord)">Cancel</x-ui.button>
@endsection

@section('content')

@php $farmer = $plantingRecord->farmer; @endphp

<div
     x-data="plantingForm(
         @js($crops->map(fn ($crop) => [
             'id'   => $crop->id,
             'name' => $crop->name,
             'hvcc' => (bool) $crop->is_hvcc,
         ])->values()),
         @js($plantingRecord->crops->map(fn ($c) => [
             'key'           => (string) $c->id,
             'crop_id'       => (string) $c->crop_id,
             'crop_specify'  => $c->crop_specify,
             'date_planted'  => optional($c->date_planted)->toDateString(),
             'area_hectares' => (string) $c->area_hectares,
         ])->values())
     )"
     class="space-y-5">

    <div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Farmer</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-muted-foreground">Name</dt><dd class="font-medium text-foreground">{{ $farmer->full_name }}</dd></div>
            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $farmer->association?->name ?? '-' }}</dd></div>
            <div><dt class="text-muted-foreground">Date Submitted</dt><dd class="font-medium text-foreground">{{ $plantingRecord->date_submitted?->format('M d, Y') }}</dd></div>
        </dl>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('mao.crop-planting.update', $plantingRecord) }}"
          data-confirm="Please review the crop, planting date and area before saving."
          data-confirm-title="Save these changes?"
          data-confirm-action="Confirm &amp; Save"
          :data-confirm-review="reviewJson()">
        @csrf
        @method('PUT')

        <x-ui.card title="Crops planted">

            <div class="space-y-5">

                <template x-for="(row, index) in rows" :key="row.key">
                    <div class="rounded-xl border border-border bg-muted/40 p-4 sm:p-5">

                        <div class="mb-4 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                <span x-text="'Crop ' + (index + 1)"></span>
                            </p>

                            <button type="button" x-show="rows.length > 1" @click="removeRow(index)"
                                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs
                                           font-medium text-destructive hover:bg-destructive/10">
                                Remove
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

                            <div class="space-y-1.5 sm:col-span-2 xl:col-span-3">
                                <label class="block text-sm font-medium">Crop Type <span class="text-destructive">*</span></label>
                                <select :name="`crops[${index}][crop_id]`" x-model="row.crop_id" required
                                        class="block h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                                    <option value="">Select a crop</option>
                                    <template x-for="crop in crops" :key="crop.id">
                                        <option :value="crop.id" x-text="crop.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="space-y-1.5 sm:col-span-2 xl:col-span-3" x-show="isHvcc(row)" x-cloak>
                                <label class="block text-sm font-medium">Which high value crop? <span class="text-destructive">*</span></label>
                                <input type="text" :name="`crops[${index}][crop_specify]`"
                                       x-model="row.crop_specify" maxlength="100" placeholder="e.g. Ampalaya"
                                       class="block h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Date Planted <span class="text-destructive">*</span></label>
                                <input type="date" :name="`crops[${index}][date_planted]`"
                                       x-model="row.date_planted" required max="{{ now()->toDateString() }}"
                                       class="block h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Area Planted (ha) <span class="text-destructive">*</span></label>
                                <input type="number" :name="`crops[${index}][area_hectares]`"
                                       x-model="row.area_hectares" required step="0.01" min="0.01"
                                       class="block h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                            </div>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addRow()"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed
                               border-input px-4 py-3 text-sm font-medium text-muted-foreground
                               transition-colors hover:border-primary hover:bg-accent hover:text-accent-foreground">
                    + Add another crop
                </button>

                <div class="flex items-center justify-between rounded-xl bg-accent px-4 py-3
                            text-sm font-medium text-accent-foreground">
                    <span>Total area planted</span>
                    <span x-text="totalArea().toFixed(2) + ' ha'"></span>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button variant="outline" :href="route('mao.crop-planting.show', $plantingRecord)">Cancel</x-ui.button>
                    <x-ui.button type="submit">Review &amp; Save</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function plantingForm(crops, initialRows) {
        return {
            crops,
            rows: (initialRows && initialRows.length) ? initialRows : [emptyRow()],

            addRow() { this.rows.push(emptyRow()); },
            removeRow(index) { this.rows.splice(index, 1); },

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

            reviewJson() {
                const rows = [];

                this.rows.forEach((row, index) => {
                    const name = this.cropName(row);
                    if (! name) return;

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
        return { key: Math.random().toString(36).slice(2), crop_id: '', crop_specify: '', date_planted: '', area_hectares: '' };
    }
</script>
@endpush
