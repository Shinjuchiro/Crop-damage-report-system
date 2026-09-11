@extends('layouts.app')

@section('title', 'Report Crop Damage')
@section('heading', 'Report Crop Damage')
@section('subheading', 'Tell the office what was damaged. Iulat po ang pinsala sa inyong pananim.')

@section('content')

{{--
    Proposal sections 29 to 38, and section 91 for the review step.

    Layout decisions worth keeping when this is defended:

    - The farmer's name is not a field. It comes from the signed in account
      and is only shown back for confirmation (section 31).
    - Crop 1 and Crop 2 are only the opening interface. Every crop block
      becomes its own row in damage_report_crops, and the same for disasters.
    - Estimated damage is the FARMER'S figure. The technician records a
      separate assessed figure later and this one is never overwritten.
    - Coordinates are optional. A phone with no signal, or no GPS at all,
      must not stop somebody reporting a flooded field.
--}}

@php
    $cropOptions = $crops->map(fn ($crop) => [
        'id'   => $crop->id,
        'name' => $crop->name,
        'hvcc' => (bool) $crop->is_hvcc,
    ])->values();

    $disasterOptions = $disasters->map(fn ($disaster) => [
        'id'    => $disaster->id,
        'label' => $disaster->name,
        'rawType' => $disaster->type,                                        // matches damage_cause
        'type'  => ucwords(str_replace('_', ' ', $disaster->type)),
        'when'  => $disaster->date_start?->format('M d, Y'),
    ])->values();

    // Weather causes are the only ones the office ever declares an event for.
    $weatherCauses = \App\Models\DamageReport::WEATHER_CAUSES;
@endphp

<div
     x-data="damageReportForm(
        @js($cropOptions),
        @js($disasterOptions),
        @js((float) ($farmer->farm_size_hectares ?? 0)),
        @js($causes),
        @js($weatherCauses),
        @js(array_values(array_intersect($causesWithEvents, $weatherCauses)))
     )">

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{--
        There used to be a warning here saying a report has to be tied to a
        disaster event, with the submit button disabled until the office had
        declared one.

        That was wrong. Insects, plant disease and extreme heat ruin crops too,
        and nobody declares an event for those, so a farmer losing a hectare to
        army worm could not file at all. Section 3 below now asks what caused
        the damage, and that question is always answerable. Linking to a
        declared event is extra information, not the gate.
    --}}

    <form method="POST" action="{{ route('farmer.reports.store') }}" enctype="multipart/form-data"
          data-confirm="Please check everything below before submitting. Once submitted, this report goes to the Municipal Agriculture Office for verification."
          data-confirm-title="Submit this damage report?"
          data-confirm-action="Confirm &amp; Submit"
          :data-confirm-review="reviewJson()">
        @csrf

        <div class="space-y-5">

            {{-- =============================================================
                 1. WHO IS REPORTING
                 Read only. Section 31: never make the farmer type their own
                 name on every report.
            ============================================================== --}}
            <x-ui.card title="1. Farmer" description="Taken from your account. Mula sa inyong account.">
                <dl class="grid gap-4 sm:grid-cols-3">
                    @php
                        $identity = [
                            'Name'        => $farmer->full_name,
                            'Association' => $farmer->association?->name ?? 'Not set',
                            'Farm size'   => $farmer->farm_size_hectares
                                                ? number_format($farmer->farm_size_hectares, 2) . ' ha'
                                                : 'Not set',
                        ];
                    @endphp

                    @foreach ($identity as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-card-foreground">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            {{-- =============================================================
                 2. DAMAGED CROPS
            ============================================================== --}}
            <x-ui.card title="2. Damaged crops"
                       description="Ano ang nasirang pananim? Add one block for every crop.">

                <div class="space-y-5">
                    <template x-for="(row, index) in crops" :key="row.key">
                        <div class="rounded-xl border border-border bg-muted/40 p-4 sm:p-5">

                            <div class="mb-4 flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                    <span x-text="'Crop ' + (index + 1)"></span>
                                    <span x-show="index === 0" class="text-destructive">*</span>
                                    <span x-show="index > 0" class="font-normal normal-case tracking-normal">(optional)</span>
                                </p>

                                <button type="button" x-show="crops.length > 1" @click="crops.splice(index, 1)"
                                        class="rounded-md px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/10">
                                    Remove
                                </button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

                                <div class="space-y-1.5 sm:col-span-2 xl:col-span-3">
                                    <label class="block text-sm font-medium">
                                        Crop Type <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">Uri ng pananim</span>
                                    </label>
                                    <select :name="`crops[${index}][crop_id]`" x-model="row.crop_id" required
                                            class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                        <option value="">Select a crop</option>
                                        <template x-for="crop in cropList" :key="crop.id">
                                            <option :value="crop.id" x-text="crop.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="space-y-1.5 sm:col-span-2 xl:col-span-3" x-show="isHvcc(row)" x-cloak>
                                    <label class="block text-sm font-medium">
                                        Which high value crop? <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">
                                            Halimbawa: Ampalaya, Talong, Mangga
                                        </span>
                                    </label>
                                    <input type="text" :name="`crops[${index}][crop_specify]`"
                                           x-model="row.crop_specify" maxlength="100"
                                           class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium">
                                        Damaged Area <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">Laki ng nasira (hektarya)</span>
                                    </label>
                                    <div class="relative">
                                        <input type="number" :name="`crops[${index}][damaged_area_hectares]`"
                                               x-model="row.damaged_area_hectares" required step="0.01" min="0.01"
                                               inputmode="decimal" placeholder="0.00"
                                               class="h-12 w-full rounded-md border border-input bg-card px-3 pr-12 text-base shadow-sm">
                                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">ha</span>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium">
                                        Date Planted <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">Petsa ng pagtatanim</span>
                                    </label>
                                    <input type="date" :name="`crops[${index}][date_planted]`"
                                           x-model="row.date_planted" required max="{{ now()->toDateString() }}"
                                           class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                </div>

                                <div class="space-y-1.5 sm:col-span-2 xl:col-span-3">
                                    <label class="block text-sm font-medium">
                                        Your Estimate of the Damage <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">
                                            Tantiya ninyo kung ilang porsyento ang nasira
                                        </span>
                                    </label>

                                    <div class="flex items-center gap-4">
                                        <input type="range" :name="`crops[${index}][estimated_damage_percent]`"
                                               x-model="row.estimated_damage_percent"
                                               min="1" max="100" step="1"
                                               class="h-2 flex-1 cursor-pointer appearance-none rounded-full bg-secondary
                                                      accent-[var(--primary)]">
                                        <span class="w-16 shrink-0 rounded-md bg-accent px-2 py-1.5 text-center
                                                     text-base font-bold text-accent-foreground"
                                              x-text="row.estimated_damage_percent + '%'"></span>
                                    </div>

                                    <p class="text-xs text-muted-foreground">
                                        A technician will visit and record their own assessment. Yours is kept as it is.
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium">
                                        Production Cost <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">Kasama ang gastos sa paggawa</span>
                                    </label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">&#8369;</span>
                                        <input type="number" :name="`crops[${index}][production_cost]`"
                                               x-model="row.production_cost" required step="0.01" min="0"
                                               inputmode="decimal" placeholder="0.00"
                                               class="h-12 w-full rounded-md border border-input bg-card pl-8 pr-3 text-base shadow-sm">
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium">
                                        Farmgate Price per Kilo <span class="text-destructive">*</span>
                                        <span class="block text-xs font-normal text-muted-foreground">Presyo kada kilo</span>
                                    </label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">&#8369;</span>
                                        <input type="number" :name="`crops[${index}][farmgate_price_per_kg]`"
                                               x-model="row.farmgate_price_per_kg" required step="0.01" min="0"
                                               inputmode="decimal" placeholder="0.00"
                                               class="h-12 w-full rounded-md border border-input bg-card pl-8 pr-3 text-base shadow-sm">
                                    </div>
                                </div>

                                {{-- Shown, not typed. The office needs to know where this
                                     number comes from, and so does the farmer. --}}
                                <div class="rounded-lg bg-card px-4 py-3 text-sm sm:col-span-2 xl:col-span-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-muted-foreground">
                                            Estimated damage cost
                                            <span class="block text-xs">
                                                production cost &times; damage percentage
                                            </span>
                                        </span>
                                        <span class="text-base font-bold text-card-foreground"
                                              x-text="peso(damageCost(row))"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addCrop()"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed
                                   border-input px-4 py-4 text-sm font-medium text-muted-foreground
                                   transition-colors hover:border-primary hover:bg-accent hover:text-accent-foreground">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Add another crop &middot; Magdagdag pa
                    </button>

                    {{-- Caught before submission rather than by the technician on site --}}
                    <div x-show="overArea()" x-cloak
                         class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-900
                                dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
                        The damaged area adds up to <strong x-text="totalArea().toFixed(2)"></strong> ha,
                        which is larger than your registered farm of
                        <strong>{{ number_format($farmer->farm_size_hectares ?? 0, 2) }}</strong> ha.
                        Please check the figures before submitting.
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-accent px-4 py-3
                                text-sm font-medium text-accent-foreground">
                        <span>Total damaged area &middot; Kabuuang nasira</span>
                        <span x-text="totalArea().toFixed(2) + ' ha'"></span>
                    </div>
                </div>
            </x-ui.card>

            {{-- =============================================================
                 3. CAUSE OF DAMAGE

                 The cause is asked first and is always answerable. Linking to
                 a declared event only appears underneath when the cause is
                 weather AND the office has actually declared an event of that
                 kind, because for pests, disease and heat there is nothing to
                 link to and asking would just be a dead end.
            ============================================================== --}}
            <x-ui.card title="3. What caused the damage?"
                       description="Ano ang sanhi ng pinsala?">

                <fieldset>
                    <legend class="sr-only">Cause of damage</legend>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($causes as $key => $label)
                            @php
                                // English before the slash, Filipino after.
                                [$english, $filipino] = array_pad(explode(' / ', $label), 2, null);
                            @endphp

                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition"
                                   :class="cause === '{{ $key }}'
                                        ? 'border-primary bg-accent shadow-sm'
                                        : 'border-border hover:border-primary/40'">

                                <input type="radio" name="damage_cause" value="{{ $key }}"
                                       x-model="cause" required class="sr-only">

                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                      :class="cause === '{{ $key }}' ? 'border-primary' : 'border-input'"
                                      aria-hidden="true">
                                    <span class="h-2.5 w-2.5 rounded-full bg-primary"
                                          x-show="cause === '{{ $key }}'"></span>
                                </span>

                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $english }}</span>
                                    @if ($filipino)
                                        <span class="block text-xs text-muted-foreground">{{ $filipino }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Only asked when they chose Other, so the office gets a real
                     answer instead of a report whose cause says "Other". --}}
                <div x-show="cause === 'other'" x-cloak class="mt-4">
                    <label for="damage_cause_other" class="mb-1.5 block text-sm font-medium">
                        Please say what it was <span class="text-destructive">*</span>
                        <span class="block text-xs font-normal text-muted-foreground">
                            Pakisabi po kung ano ang sanhi
                        </span>
                    </label>
                    <input id="damage_cause_other" type="text" name="damage_cause_other" maxlength="120"
                           x-model="causeOther" value="{{ old('damage_cause_other') }}"
                           placeholder="e.g. Hailstorm, landslide, saltwater intrusion"
                           class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                </div>

                {{-- ---------- Declared events ---------- --}}
                <div x-show="needsEvent()" x-cloak class="mt-5 border-t border-border pt-5">
                    <p class="text-sm font-semibold">
                        Which event was it? <span class="text-destructive">*</span>
                    </p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        The office has recorded events of this kind. Choosing one puts your report together
                        with everybody else's from the same event.
                        <span class="mt-0.5 block text-xs">
                            Piliin po ang kaganapan upang mabilang ang inyong ulat kasama ng iba.
                        </span>
                    </p>

                    <div class="mt-3 space-y-3">
                        <template x-for="(row, index) in disasters" :key="row.key">
                            <div class="rounded-xl border border-border bg-muted/40 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                        <span x-text="'Event ' + (index + 1)"></span>
                                        <span x-show="index > 0" class="font-normal normal-case tracking-normal">(optional)</span>
                                    </p>

                                    <button type="button" x-show="disasters.length > 1" @click="disasters.splice(index, 1)"
                                            class="rounded-md px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/10">
                                        Remove
                                    </button>
                                </div>

                                {{-- Filtered to events that match the chosen cause.
                                     Showing every typhoon on record when the farmer
                                     said "flood" just makes the list harder to read. --}}
                                <select :name="`disasters[${index}]`" x-model="row.id"
                                        class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                    <option value="">Select an event</option>
                                    <template x-for="disaster in eventsForCause()" :key="disaster.id">
                                        <option :value="disaster.id"
                                                x-text="disaster.label + (disaster.when ? ' (' + disaster.when + ')' : '')">
                                        </option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <button type="button" @click="addDisaster()"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed
                                       border-input px-4 py-3 text-sm font-medium text-muted-foreground
                                       transition-colors hover:border-primary hover:bg-accent hover:text-accent-foreground">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Add another event
                        </button>
                    </div>
                </div>

                {{-- Weather cause, but the office has not declared anything of
                     that kind. Say so rather than showing an empty dropdown. --}}
                <p x-show="cause && weatherCauses.includes(cause) && ! needsEvent()" x-cloak
                   class="mt-4 rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                    The office has not recorded an event for this yet. Your report will still be submitted,
                    and the office can attach the event later.
                    <span class="mt-0.5 block text-xs">
                        Maipapasa pa rin po ang inyong ulat.
                    </span>
                </p>
            </x-ui.card>

            {{-- =============================================================
                 4. PHOTOS
            ============================================================== --}}
            <x-ui.card title="4. Photos of the damage"
                       description="Mga larawan ng pinsala. Up to 10 photos, 5 MB each.">

                <div class="space-y-4">
                    {{-- capture="environment" opens the rear camera straight away
                         on a phone, which is how most of these will be taken. --}}
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl
                                  border-2 border-dashed border-input px-4 py-8 text-center
                                  transition-colors hover:border-primary hover:bg-accent">
                        <svg class="h-9 w-9 text-muted-foreground" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                            <circle cx="12" cy="13" r="3.5"/>
                        </svg>
                        <span class="text-sm font-semibold text-foreground">Take or choose photos</span>
                        <span class="text-xs text-muted-foreground">Kumuha o pumili ng larawan</span>

                        <input type="file" name="photos[]" multiple accept="image/*" capture="environment"
                               x-ref="photos" @change="onPhotos($event)" class="hidden">
                    </label>

                    {{-- Section 91.7: previews, with the ability to remove one,
                         before anything is submitted. --}}
                    <div x-show="previews.length" x-cloak class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <template x-for="(preview, index) in previews" :key="preview.key">
                            <div class="group relative aspect-square overflow-hidden rounded-lg border border-border">
                                <img :src="preview.url" alt="" class="h-full w-full object-cover">

                                <button type="button" @click="removePhoto(index)"
                                        class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center
                                               rounded-full bg-slate-900/70 text-white"
                                        :aria-label="'Remove photo ' + (index + 1)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                                         stroke-linecap="round" viewBox="0 0 24 24">
                                        <path d="M6 6l12 12M18 6L6 18"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-muted-foreground" x-show="previews.length" x-cloak>
                        <span x-text="previews.length"></span> photo(s) ready to send.
                    </p>
                </div>
            </x-ui.card>

            {{-- =============================================================
                 5. LOCATION
            ============================================================== --}}
            <x-ui.card title="5. Where is the farm?"
                       description="Saan po matatagpuan ang sakahan?">

                <div class="space-y-5">

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium">
                            Barangay
                            <span class="block text-xs font-normal text-muted-foreground">
                                Defaults to the barangay on your profile
                            </span>
                        </label>
                        <select name="reported_barangay_id"
                                class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}"
                                        @selected(old('reported_barangay_id', $farmer->barangay_id) == $barangay->id)>
                                    {{ $barangay->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium">
                            Describe how to find the farm <span class="text-destructive">*</span>
                            <span class="block text-xs font-normal text-muted-foreground">
                                Ilarawan kung paano mapupuntahan ang sakahan
                            </span>
                        </label>
                        <textarea name="farm_location_description" rows="3" required maxlength="500"
                                  placeholder="e.g. Near the chapel along the main road, second field after the creek"
                                  class="w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm">{{ old('farm_location_description') }}</textarea>
                        <p class="text-xs text-muted-foreground">
                            This matters most when there is no GPS signal, so please be specific.
                        </p>
                    </div>

                    <x-ui.separator label="Coordinates (optional)" />

                    <div class="space-y-3">
                        <x-ui.button type="button" size="lg" variant="outline" class="w-full"
                                     x-on:click="captureLocation()" x-bind:disabled="locating">
                            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11z"/>
                                <circle cx="12" cy="10" r="2.5"/>
                            </svg>
                            <span x-show="! locating">Capture my current location</span>
                            <span x-show="locating" x-cloak>Finding your location...</span>
                        </x-ui.button>

                        <p x-show="locationError" x-cloak
                           class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900
                                  dark:bg-amber-950/60 dark:text-amber-200"
                           x-text="locationError"></p>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Latitude</label>
                                <input type="number" name="reported_latitude" step="0.0000001"
                                       x-model="latitude" @input="source = 'manual'"
                                       placeholder="14.3900000" inputmode="decimal"
                                       class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Longitude</label>
                                <input type="number" name="reported_longitude" step="0.0000001"
                                       x-model="longitude" @input="source = 'manual'"
                                       placeholder="120.8500000" inputmode="decimal"
                                       class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                            </div>
                        </div>

                        <input type="hidden" name="location_source" :value="source">

                        <p class="text-xs text-muted-foreground">
                            You can submit without coordinates. The technician will pin the exact location
                            during the field inspection, and your entry is kept separately from theirs.
                        </p>
                    </div>
                </div>
            </x-ui.card>

            {{-- =============================================================
                 6. REMARKS
            ============================================================== --}}
            <x-ui.card title="6. Anything else the office should know?"
                       description="May iba pa po bang nais ninyong idagdag? Optional.">
                <textarea name="description" rows="4" maxlength="2000"
                          placeholder="e.g. The water stayed on the field for three days"
                          class="w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm">{{ old('description') }}</textarea>
            </x-ui.card>

            {{-- Submit --}}
            <div class="flex flex-col-reverse gap-3 pb-4 sm:flex-row sm:justify-end">
                <x-ui.button size="lg" variant="outline" :href="route('farmer.dashboard')">Cancel</x-ui.button>
                {{-- Never disabled any more. A farmer can always file, whatever
                     ruined the crop and whether or not the office has declared
                     an event for it. --}}
                <x-ui.button size="lg" type="submit">Review &amp; Submit Report</x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function damageReportForm(cropList, disasterList, farmSize, causeList, weatherCauses, causesWithEvents) {
        return {
            cropList,
            disasterList,
            farmSize,

            // causeList        every cause, key to label
            // weatherCauses    the ones the office declares events for
            // causesWithEvents the ones that actually have an event on record
            causeList,
            weatherCauses,
            causesWithEvents,

            cause: '',
            causeOther: '',

            crops: [emptyCrop(), emptyCrop()],

            // One event row to start with, not two. Most reports link to a
            // single event, and the second empty dropdown just looked like
            // something else that had to be filled in.
            disasters: [emptyDisaster()],

            /* ---------- cause ---------- */

            /**
             * Do we ask which declared event it was?
             *
             * Only when the cause is weather AND the office has actually
             * recorded an event of that kind. Otherwise there is nothing to
             * choose from and asking would be a dead end.
             */
            needsEvent() {
                return this.causesWithEvents.includes(this.cause);
            },

            /** The declared events that match the chosen cause. */
            eventsForCause() {
                return this.disasterList.filter(d => d.rawType === this.cause);
            },

            causeLabel() {
                if (this.cause === 'other') {
                    return this.causeOther || 'Other';
                }

                // The stored labels read "Typhoon / Bagyo".
                return this.causeList[this.cause] || 'Not chosen';
            },

            previews: [],
            latitude: '',
            longitude: '',
            source: 'none',
            locating: false,
            locationError: '',

            /* ---------- crops ---------- */
            addCrop() { this.crops.push(emptyCrop()); },
            addDisaster() { this.disasters.push(emptyDisaster()); },

            crop(row) {
                return this.cropList.find(c => String(c.id) === String(row.crop_id));
            },

            isHvcc(row) {
                const crop = this.crop(row);

                return crop ? crop.hvcc : false;
            },

            damageCost(row) {
                const cost = parseFloat(row.production_cost) || 0;
                const percent = parseFloat(row.estimated_damage_percent) || 0;

                return cost * (percent / 100);
            },

            totalArea() {
                return this.crops.reduce(
                    (sum, row) => sum + (parseFloat(row.damaged_area_hectares) || 0), 0
                );
            },

            overArea() {
                return this.farmSize > 0 && this.totalArea() > this.farmSize + 0.001;
            },

            peso(value) {
                return '₱' + value.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },

            /* ---------- photos ----------
               The previews and the file input are kept in step by rebuilding
               the input's FileList through a DataTransfer, which is the only
               way to remove one file from a multiple input. */
            onPhotos(event) {
                this.previews.forEach(preview => URL.revokeObjectURL(preview.url));

                this.previews = Array.from(event.target.files).map(file => ({
                    key: file.name + file.size + file.lastModified,
                    url: URL.createObjectURL(file),
                }));
            },

            removePhoto(index) {
                const input = this.$refs.photos;
                const kept = new DataTransfer();

                Array.from(input.files).forEach((file, i) => {
                    if (i !== index) {
                        kept.items.add(file);
                    }
                });

                input.files = kept.files;

                URL.revokeObjectURL(this.previews[index].url);
                this.previews.splice(index, 1);
            },

            /* ---------- location ---------- */
            captureLocation() {
                this.locationError = '';

                if (! navigator.geolocation) {
                    this.locationError = 'This phone or browser cannot give a location. '
                        + 'Please describe the farm above instead.';

                    return;
                }

                this.locating = true;

                navigator.geolocation.getCurrentPosition(
                    position => {
                        this.latitude = position.coords.latitude.toFixed(7);
                        this.longitude = position.coords.longitude.toFixed(7);
                        this.source = 'gps';
                        this.locating = false;
                    },
                    error => {
                        this.locating = false;
                        this.locationError = error.code === error.PERMISSION_DENIED
                            ? 'Location permission was refused. You can type the coordinates, '
                              + 'or just describe the farm above.'
                            : 'Your location could not be found right now. You can still submit '
                              + 'without coordinates.';
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            },

            /* ---------- the review the farmer confirms ---------- */
            reviewJson() {
                const rows = [];

                this.crops.forEach((row, index) => {
                    const crop = this.crop(row);

                    if (! crop) {
                        return;
                    }

                    const label = 'Crop ' + (index + 1);
                    const name = row.crop_specify ? crop.name + ' (' + row.crop_specify + ')' : crop.name;

                    rows.push({ label: label, value: name });
                    rows.push({
                        label: label + ' damaged area',
                        value: (parseFloat(row.damaged_area_hectares) || 0).toFixed(2) + ' ha',
                    });
                    rows.push({
                        label: label + ' your damage estimate',
                        value: (row.estimated_damage_percent || 0) + '%',
                    });
                    rows.push({
                        label: label + ' production cost',
                        value: this.peso(parseFloat(row.production_cost) || 0),
                    });
                    rows.push({
                        label: label + ' estimated damage cost',
                        value: this.peso(this.damageCost(row)),
                    });
                });

                // The cause goes near the top of the review, because it is the
                // one answer that decides how the office reads everything else.
                rows.push({ label: 'Cause of damage', value: this.causeLabel() });

                const chosen = this.disasters
                    .map(row => this.disasterList.find(d => String(d.id) === String(row.id)))
                    .filter(Boolean)
                    .map(d => d.type + ' - ' + d.label);

                if (chosen.length) {
                    rows.push({ label: 'Declared event(s)', value: chosen.join(', ') });
                }

                rows.push({ label: 'Total damaged area', value: this.totalArea().toFixed(2) + ' ha' });
                rows.push({ label: 'Photos attached', value: String(this.previews.length) });
                rows.push({
                    label: 'Location',
                    value: this.latitude && this.longitude
                        ? this.latitude + ', ' + this.longitude
                          + (this.source === 'gps' ? ' (GPS)' : ' (typed in)')
                        : 'Described in words only',
                });

                return JSON.stringify(rows);
            },
        };
    }

    function emptyCrop() {
        return {
            key: Math.random().toString(36).slice(2),
            crop_id: '',
            crop_specify: '',
            damaged_area_hectares: '',
            date_planted: '',
            estimated_damage_percent: 50,
            production_cost: '',
            farmgate_price_per_kg: '',
        };
    }

    function emptyDisaster() {
        return { key: Math.random().toString(36).slice(2), id: '' };
    }
</script>
@endpush
