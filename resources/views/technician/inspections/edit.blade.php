@extends('layouts.app')

@section('title', 'Field Inspection & Validation')
@section('heading', 'On-site Validation')
@section('heading-fil', 'Pagsusuri sa Sakahan')
@section('subheading', 'Report ' . $report->reference . ' for ' . ($report->farmer?->full_name ?? 'a farmer'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('technician.reports.show', $report)">
        Back to report
    </x-ui.button>
@endsection

@section('content')

{{--
    Proposal sections 42 to 52, plus 91.5 for the review step.

    ONE form, TWO layouts:

      Phone   a four step wizard, exactly as in the approved mockup. One
              thing on screen at a time, big targets, and you cannot move
              forward until that step is actually answered. This is used
              outdoors, one handed, sometimes in the sun.

      Laptop  the same four panels, all open at once, because there is
              room and it is faster to review that way.

    Both share the same inputs and the same Alpine state, so there is only
    one version of the truth. The "wide" flag below is what decides which
    layout you get, and it updates if a tablet is rotated.

    Nothing saves until the confirmation dialog. The review on step 4 and
    the dialog after it are two different things on purpose: step 4 is
    where you read your own answers back, the dialog is the last chance to
    stop (section 91.15, no accidental transactions).
--}}

@php
    // The farmer's own figure, averaged across the crops on this report.
    // Shown throughout so the technician can see what they are agreeing or
    // disagreeing with. It is never overwritten (section 45).
    $farmerEstimate = round($report->crops->avg('estimated_damage_percent'));

    $steps = [
        1 => ['Capture Photos',        'Kumuha ng malinaw na larawan ng mga nasirang pananim.'],
        2 => ['Assess Damage Severity', 'Piliin ang damage level batay sa aktwal na kondisyon ng pananim.'],
        3 => ['Pin the Farm Location',  'I-pin ang mapa o ilagay ang coordinates.'],
        4 => ['Review and Submit',      'I-review ang impormasyon bago isumite ang validasyon.'],
    ];
@endphp

<div x-data="inspectionForm({
        reportedLat: {{ $report->reported_latitude ?? 'null' }},
        reportedLng: {{ $report->reported_longitude ?? 'null' }},
        farmerEstimate: {{ $farmerEstimate }},
        notesGap: {{ $notesGap }},
        initialDisasterIds: {!! json_encode($report->disasters->pluck('id')->map(fn ($id) => (string) $id)->values()) !!},
        disasterLabels: {!! json_encode($availableDisasters->pluck('name', 'id')) !!}
     })">

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-4">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- =================================================================
         PHONE: STEP HEADER
         Tells you where you are and how much is left, which is the whole
         reason for splitting the form up in the first place.
    ================================================================== --}}
    <div class="mb-4 lg:hidden">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm font-semibold">On-site Validation</p>
            <p class="text-xs text-muted-foreground">
                Step <span x-text="step"></span> of {{ count($steps) }}
            </p>
        </div>

        {{-- Progress bar. Four segments so it is countable at a glance
             rather than a smooth bar you have to estimate. --}}
        <div class="mt-2 flex gap-1.5" role="progressbar" aria-valuemin="1"
             aria-valuemax="{{ count($steps) }}" :aria-valuenow="step">
            @foreach (array_keys($steps) as $number)
                <span class="h-1.5 flex-1 rounded-full transition-colors"
                      :class="step >= {{ $number }} ? 'bg-primary' : 'bg-secondary'"></span>
            @endforeach
        </div>
    </div>

    {{-- What the farmer said, kept in view while you fill this in.
         Collapsed on a phone so it does not push the actual work off screen. --}}
    <details class="mb-4 lg:hidden">
        <summary class="cursor-pointer rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium">
            What the farmer reported
        </summary>
        <div class="rounded-b-lg border border-t-0 border-border bg-card px-4 py-3 text-sm">
            <p><span class="text-muted-foreground">Crops:</span>
               {{ $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', ') }}</p>
            <p class="mt-1"><span class="text-muted-foreground">Damaged area:</span>
               {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha</p>
            <p class="mt-1"><span class="text-muted-foreground">Farmer's estimate:</span>
               {{ $farmerEstimate }}%</p>
            <p class="mt-1"><span class="text-muted-foreground">Location given:</span>
               {{ $report->farm_location_description }}</p>
        </div>
    </details>

    <x-ui.card title="What the farmer reported" class="mb-4 hidden lg:block"
               description="Read only. Your findings go in the form below.">
        <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Crops</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @foreach ($report->crops as $crop)
                        <x-ui.badge variant="primary">{{ $crop->crop_specify ?: $crop->crop?->name }}</x-ui.badge>
                    @endforeach
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Damaged area</dt>
                <dd class="mt-1 text-sm font-medium">
                    {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Farmer's estimate</dt>
                <dd class="mt-1 text-sm font-medium">{{ $farmerEstimate }}%</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Location given</dt>
                <dd class="mt-1 text-sm">{{ $report->farm_location_description }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <form method="POST" action="{{ route('technician.inspection.update', $report) }}"
          enctype="multipart/form-data"
          data-confirm="Please review all inspection information, photos, damage severity, notes and verified location before submitting. Once submitted, the inspection will be recorded."
          data-confirm-title="Confirm inspection submission?"
          data-confirm-action="Confirm &amp; Submit"
          :data-confirm-review="reviewJson()">
        @csrf
        @method('PUT')

        <div class="space-y-4">

            {{-- =============================================================
                 STEP 1  CAPTURE PHOTOS  (section 43)
            ============================================================== --}}
            <div x-show="wide || step === 1" x-cloak>
                <x-ui.card title="1. Capture Photos" description="Kumuha ng malinaw na larawan ng mga nasirang pananim.">

                    {{-- Guidelines. Written as instructions rather than rules,
                         because this is training material for a technician who
                         may be doing their first inspection. --}}
                    <div class="rounded-xl border border-primary/30 bg-accent px-4 py-3">
                        <p class="text-sm font-semibold text-accent-foreground">Photo Guidelines</p>
                        <ul class="mt-1.5 list-inside list-disc space-y-1 text-sm text-accent-foreground/90">
                            <li>Kumuha ng wide shot ng buong apektadong lugar</li>
                            <li>Kumuha ng close-up shot ng mismong nasirang pananim</li>
                            <li>Siguraduhing malinaw ang kuha ng pinsala</li>
                        </ul>
                    </div>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">

                        {{-- Two named slots. Naming them is what lets the record
                             still make sense a year from now: shot_type is saved
                             with each file, so nobody has to guess which photo
                             was the wide one. --}}
                        @foreach ([
                            ['wide',    'Wide Shot',      'photo_wide',    true,  'Buong sakahan'],
                            ['closeup', 'Close-up Shot',  'photo_closeup', false, 'Mismong pananim'],
                        ] as [$key, $label, $field, $required, $filipino])

                            <div>
                                <p class="mb-2 text-sm font-semibold">
                                    {{ $label }}
                                    @if ($required)
                                        <span class="text-destructive">*</span>
                                    @else
                                        <span class="font-normal text-muted-foreground">(recommended)</span>
                                    @endif
                                    <span class="block text-xs font-normal text-muted-foreground">{{ $filipino }}</span>
                                </p>

                                <div class="flex items-stretch gap-3">

                                    {{-- The preview, or an empty frame so the row
                                         does not jump around once a photo lands. --}}
                                    <div class="relative h-28 w-36 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
                                        <template x-if="shots.{{ $key }}">
                                            <img :src="shots.{{ $key }}" alt="{{ $label }} preview"
                                                 class="h-full w-full object-cover">
                                        </template>

                                        <template x-if="! shots.{{ $key }}">
                                            <span class="flex h-full w-full items-center justify-center text-muted-foreground">
                                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5"
                                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                    <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                                    <circle cx="12" cy="13" r="3.5"/>
                                                </svg>
                                            </span>
                                        </template>

                                        {{-- Clearing a slot has to also clear the
                                             file input, or the old file is still
                                             what gets uploaded. --}}
                                        <button type="button" x-show="shots.{{ $key }}" x-cloak
                                                @click="clearShot('{{ $key }}', $refs.{{ $key }})"
                                                class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center
                                                       rounded-full bg-slate-900/70 text-white"
                                                aria-label="Remove {{ $label }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                                                 stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    </div>

                                    <label class="flex flex-1 cursor-pointer flex-col items-center justify-center gap-1.5
                                                  rounded-lg border-2 border-dashed border-input px-3 py-4 text-center
                                                  transition-colors hover:border-primary hover:bg-accent">
                                        <svg class="h-7 w-7 text-muted-foreground" fill="none" stroke="currentColor"
                                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                            <circle cx="12" cy="13" r="3.5"/>
                                        </svg>
                                        <span class="text-sm font-semibold"
                                              x-text="shots.{{ $key }} ? 'Retake Photo' : 'Take Photo'"></span>

                                        {{-- capture="environment" opens the rear
                                             camera straight away, which is how
                                             these will actually be taken. --}}
                                        <input type="file" name="{{ $field }}" accept="image/*" capture="environment"
                                               x-ref="{{ $key }}" @change="onShot('{{ $key }}', $event)" class="hidden">
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Anything else worth recording. Optional, and separate
                         from the two named shots so it cannot be mistaken for
                         one of them. --}}
                    <div class="mt-5 border-t border-border pt-4">
                        <p class="mb-2 text-sm font-semibold">
                            Additional photos
                            <span class="font-normal text-muted-foreground">(optional)</span>
                        </p>

                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2
                                      border-dashed border-input px-4 py-3 text-sm font-medium
                                      transition-colors hover:border-primary hover:bg-accent">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Add more photos
                            <input type="file" name="photos[]" multiple accept="image/*" capture="environment"
                                   x-ref="photos" @change="onPhotos($event)" class="hidden">
                        </label>

                        <div x-show="previews.length" x-cloak class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5 xl:grid-cols-8">
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
                    </div>
                </x-ui.card>
            </div>

            {{-- =============================================================
                 STEP 2  SEVERITY AND ASSESSED PERCENTAGE  (sections 44 to 46)
            ============================================================== --}}
            <div x-show="wide || step === 2" x-cloak class="space-y-4">

                <x-ui.card title="2. Assess Damage Severity"
                           description="Piliin ang damage level batay sa aktwal na kondisyon ng pananim.">

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($severities as $key => $band)
                            <label class="relative flex cursor-pointer flex-col gap-1 rounded-xl border-2 p-4 transition"
                                   :class="severity === '{{ $key }}'
                                        ? 'border-primary bg-accent shadow-sm'
                                        : 'border-border hover:border-primary/40'">

                                {{-- No HTML "required" here. On a phone the other
                                     steps are hidden while you are on step 4, and
                                     the browser refuses to submit a form with a
                                     required field it cannot scroll to, without
                                     saying why. Alpine checks it per step instead
                                     and the controller checks it again. --}}
                                <input type="radio" name="severity" value="{{ $key }}" x-model="severity"
                                       class="sr-only">

                                <span class="flex items-center gap-2">
                                    {{-- The colour dot uses the same scale as the
                                         map legend, so Partial looks the same
                                         everywhere in the system. --}}
                                    <span class="h-3.5 w-3.5 shrink-0 rounded-full"
                                          style="background: {{ $band['color'] }}"></span>
                                    <span class="text-base font-bold">{{ $band['label'] }} Damage</span>
                                </span>

                                <span class="text-sm font-medium text-muted-foreground">
                                    {{ $band['range'] }} ang Pinsala
                                </span>

                                <span class="text-xs text-muted-foreground">
                                    @switch($key)
                                        @case('slight') Minor damage. @break
                                        @case('moderate') Noticeable damage to part of the crop. @break
                                        @case('partial') Significant damage to a substantial portion. @break
                                        @case('total') The crop is completely or almost completely destroyed. @break
                                    @endswitch
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-ui.card>

                {{-- Section 45. Kept on the same step as severity on purpose:
                     the two have to agree, and the warning below only makes
                     sense if you can see both at once. --}}
                <x-ui.card title="Your assessed damage percentage"
                           description="This is recorded beside the farmer's estimate. It never replaces it.">

                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <input type="range" name="assessed_damage_percent" x-model="percent"
                                       min="1" max="100" step="1"
                                       class="h-2 flex-1 cursor-pointer appearance-none rounded-full bg-secondary
                                              accent-[var(--primary)]">
                                <span class="w-20 shrink-0 rounded-md bg-accent px-2 py-2 text-center text-lg
                                             font-bold text-accent-foreground" x-text="percent + '%'"></span>
                            </div>

                            {{-- Caught here as well as on the server, so the
                                 technician finds out now and not after tapping
                                 Submit at the end of the wizard. --}}
                            <p x-show="! bandMatches()" x-cloak
                               class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900
                                      dark:bg-amber-950/60 dark:text-amber-200">
                                That percentage does not match the severity you chose.
                                <span x-text="bandHint()"></span>
                            </p>
                        </div>

                        {{-- Side by side, because the gap between the two figures
                             is the thing the office will look at. --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-border bg-muted p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Farmer estimated
                                </p>
                                <p class="mt-1 text-2xl font-bold">{{ $farmerEstimate }}%</p>
                            </div>
                            <div class="rounded-xl border border-primary/40 bg-accent p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-accent-foreground/70">
                                    You assessed
                                </p>
                                <p class="mt-1 text-2xl font-bold text-accent-foreground" x-text="percent + '%'"></p>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Section 46. Optional most of the time, as in the mockup.
                     It only becomes required when you are contradicting the
                     farmer by a wide margin, because that is the case where
                     the office and the farmer both deserve a reason. --}}
                <x-ui.card>
                    <label for="notes" class="block text-base font-semibold">
                        Notes
                        <span x-show="! notesRequired()" class="font-normal text-muted-foreground">(Optional)</span>
                        <span x-show="notesRequired()" x-cloak class="font-normal text-destructive">(Required)</span>
                    </label>

                    <p x-show="notesRequired()" x-cloak
                       class="mt-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900
                              dark:bg-amber-950/60 dark:text-amber-200">
                        Your assessment is <span x-text="gap()"></span> points away from the farmer's estimate
                        of {{ $farmerEstimate }}%. Please write what you actually found.
                    </p>

                    <textarea id="notes" name="notes" rows="4" maxlength="2000" x-model="notes"
                              placeholder="Maglagay ng karagdagang obserbasyon. Halimbawa: Humigit kumulang 75% ng palay ang nasira dahil sa baha. Tatlong araw nanatili ang tubig sa bukid."
                              class="mt-2 w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm"></textarea>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Describe the actual condition, the extent of the damage, and any difference between
                        what the farmer reported and what you found. This is what the office reads when deciding.
                    </p>
                </x-ui.card>

                {{-- Optional. Lets the technician link a disaster event the
                     farmer never had the chance to (it may not have been
                     declared yet when they filed), or fix one they picked
                     wrong. Every currently active event is offered here
                     regardless of the report's own cause, since a
                     technician standing on the farm may simply know better
                     than the dropdown the farmer picked from home. --}}
                <x-ui.card title="Disaster Event"
                           description="Optional. Add an event the farmer could not link yet, or fix one that is wrong.">

                    @if ($availableDisasters->isEmpty())
                        <p class="text-sm text-muted-foreground">
                            The office has not declared any active disaster events yet.
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach ($availableDisasters as $disaster)
                                @php $linkedByFarmer = optional($report->disasters->firstWhere('id', $disaster->id))->pivot?->linked_by_role === 'farmer'; @endphp

                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-border px-3 py-2.5
                                              transition hover:border-primary/40"
                                       :class="disasterIds.includes('{{ $disaster->id }}') ? 'border-primary bg-accent' : ''">
                                    <input type="checkbox" name="disasters[]" value="{{ $disaster->id }}"
                                           x-model="disasterIds" class="mt-0.5 h-4 w-4 shrink-0 accent-[var(--primary)]">

                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium">{{ $disaster->name }}</span>
                                            @if ($linkedByFarmer)
                                                <span class="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                                    Farmer linked this
                                                </span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-muted-foreground">
                                            {{ ucfirst($disaster->type) }}
                                            @if ($disaster->date_start)
                                                &middot; {{ $disaster->date_start->format('M d, Y') }}
                                                @if ($disaster->date_end) - {{ $disaster->date_end->format('M d, Y') }} @endif
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-muted-foreground">
                            Unchecking one the farmer already linked genuinely removes it, so only uncheck it
                            if you are sure it is wrong - it will no longer count toward that event's totals.
                        </p>
                    @endif
                </x-ui.card>
            </div>

            {{-- =============================================================
                 STEP 3  VERIFIED LOCATION  (sections 47 to 49)
            ============================================================== --}}
            <div x-show="wide || step === 3" x-cloak>
                <x-ui.card title="3. Pin Your Map Location or Enter Coordinates"
                           description="Ang totoong kinalalagyan ng sakahan.">

                    <div class="grid gap-6 lg:grid-cols-2">

                        {{-- Map first on a phone, because tapping it is the
                             fastest way to answer this, and the coordinate
                             boxes below are the fallback. --}}
                        <div class="order-1 lg:order-2">
                            <div id="verifyMap" class="h-72 w-full rounded-xl border border-border lg:h-80"></div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Tap the map or drag the pin to set the exact spot.
                            </p>
                        </div>

                        <div class="order-2 space-y-4 lg:order-1">
                            @if ($report->has_reported_coordinates)
                                <div class="rounded-lg bg-muted px-4 py-3 text-sm">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        Farmer reported
                                    </p>
                                    <p class="mt-1 font-medium">
                                        {{ $report->reported_latitude }}, {{ $report->reported_longitude }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Your pin is stored separately. The farmer's is never overwritten.
                                    </p>
                                </div>
                            @endif

                            <x-ui.button type="button" size="lg" variant="outline" class="w-full"
                                         x-on:click="captureLocation()" x-bind:disabled="locating">
                                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11z"/>
                                    <circle cx="12" cy="10" r="2.5"/>
                                </svg>
                                <span x-show="! locating">Use My Current Location</span>
                                <span x-show="locating" x-cloak>Finding your location...</span>
                            </x-ui.button>

                            <p x-show="locationError" x-cloak
                               class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900
                                      dark:bg-amber-950/60 dark:text-amber-200"
                               x-text="locationError"></p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium" for="latitude">
                                        Latitude <span class="text-destructive">*</span>
                                    </label>
                                    <input id="latitude" type="number" name="latitude" step="0.0000001"
                                           x-model="latitude" @input="source = 'manual'" inputmode="decimal"
                                           class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium" for="longitude">
                                        Longitude <span class="text-destructive">*</span>
                                    </label>
                                    <input id="longitude" type="number" name="longitude" step="0.0000001"
                                           x-model="longitude" @input="source = 'manual'" inputmode="decimal"
                                           class="h-12 w-full rounded-md border border-input bg-card px-3 text-base shadow-sm">
                                </div>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Whichever way you set it, the coordinates above are what gets saved as the
                                technician verified location.
                            </p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- =============================================================
                 STEP 4  SUMMARY  (sections 50 and 91.5)
            ============================================================== --}}
            <div x-show="wide || step === 4" x-cloak>
                <x-ui.card title="4. Summary" description="I-review ang impormasyon bago isumite ang validasyon.">

                    <dl class="divide-y divide-border rounded-xl border border-border">
                        @php
                            /* Label on the left, value on the right. The values
                               that can still change are read out of Alpine, so
                               this panel is never stale. */
                            $fixed = [
                                'Report ID'      => $report->reference,
                                'Farmer'         => $report->farmer?->full_name ?? 'Unknown',
                                'Location'       => $report->reportedBarangay?->name
                                                    ?? $report->farmer?->barangay?->name ?? 'Not set',
                                'Crop'           => $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', ') ?: 'Not set',
                                'Validation Date'=> now()->format('F d, Y'),
                            ];
                        @endphp

                        @foreach ($fixed as $label => $value)
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-sm text-muted-foreground">{{ $label }}</dt>
                                <dd class="text-right text-sm font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach

                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Damage Severity</dt>
                            <dd class="text-right text-sm font-semibold"
                                x-text="severityLabel() + (severity ? ' (' + bandRange() + ')' : '')"></dd>
                        </div>

                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Farmer / You</dt>
                            <dd class="text-right text-sm font-medium">
                                {{ $farmerEstimate }}% <span class="text-muted-foreground">/</span>
                                <span x-text="percent + '%'"></span>
                            </dd>
                        </div>

                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Inspection Photos</dt>
                            <dd class="text-right text-sm font-medium" x-text="photoSummary()"></dd>
                        </div>

                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Verified Coordinates</dt>
                            <dd class="text-right text-sm font-medium"
                                x-text="latitude && longitude ? latitude + ', ' + longitude : 'Not set'"></dd>
                        </div>

                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Disaster Event</dt>
                            <dd class="text-right text-sm font-medium" x-text="disasterSummary()"></dd>
                        </div>

                        <div class="px-4 py-3">
                            <dt class="text-sm text-muted-foreground">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm"
                                x-text="notes || 'No notes written.'"></dd>
                        </div>
                    </dl>

                    {{-- Green when everything is answered, amber when it is not,
                         with the missing item named. A tick that appears no
                         matter what would be worse than no tick at all. --}}
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
                                Lahat ng impormasyon ay tama at kumpleto!
                                <span class="mt-0.5 block text-xs opacity-80">
                                    Everything is filled in. Submit when you are ready.
                                </span>
                            </span>
                            <span x-show="! isComplete()" x-text="whatIsMissing()"></span>
                        </p>
                    </div>
                </x-ui.card>
            </div>

            {{-- =============================================================
                 NAVIGATION
            ============================================================== --}}

            {{-- Phone: Back and Next, or Submit on the last step.
                 The bar sits above the bottom navigation, hence the offset. --}}
            <div class="sticky bottom-20 z-10 -mx-3 border-t border-border bg-card px-3 py-3 lg:hidden">
                <div class="flex gap-3">
                    <x-ui.button type="button" size="lg" variant="outline" class="flex-1"
                                 @click="back()" x-bind:disabled="step === 1">
                        Back
                    </x-ui.button>

                    <x-ui.button type="button" size="lg" class="flex-1"
                                 x-show="step < {{ count($steps) }}" @click="next()">
                        Next
                    </x-ui.button>

                    {{-- Disabled until every step is answered. The amber panel
                         just above says exactly what is missing, so this is
                         never a dead button with no explanation. --}}
                    <x-ui.button type="submit" size="lg" class="flex-1"
                                 x-show="step === {{ count($steps) }}" x-cloak
                                 x-bind:disabled="! isComplete()">
                        Submit
                    </x-ui.button>
                </div>

                {{-- Says exactly what is stopping you rather than a button
                     that silently does nothing when pressed. --}}
                <p x-show="stepError" x-cloak x-text="stepError"
                   class="mt-2 text-center text-xs text-destructive"></p>
            </div>

            {{-- Laptop: everything is already on screen, so one submit is enough. --}}
            <div class="hidden flex-col-reverse gap-3 pb-4 sm:flex-row sm:justify-end lg:flex">
                <x-ui.button size="lg" variant="outline" :href="route('technician.reports.show', $report)">
                    Cancel
                </x-ui.button>
                <x-ui.button size="lg" type="submit" x-bind:disabled="! isComplete()">
                    Confirm &amp; Submit Inspection
                </x-ui.button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
    /*
     * All of the inspection form's state in one place.
     *
     * The same object drives the phone wizard and the laptop page. The only
     * difference between them is the "wide" flag, which decides whether the
     * step panels are shown one at a time or all at once.
     */
    function inspectionForm(config) {
        return {
            /* ---------- which layout, which step ---------- */
            step: 1,
            lastStep: 4,
            stepError: '',
            wide: window.matchMedia('(min-width: 1024px)').matches,

            /* ---------- the answers ---------- */
            severity: '',
            percent: 50,
            notes: '',

            // Object URLs for the two named shots, so the technician can see
            // what they just took without waiting for an upload.
            shots: { wide: null, closeup: null },
            previews: [],

            // Start from the farmer's coordinates when they gave any. It is a
            // starting point to correct, not an answer: the technician still
            // has to capture or move the pin.
            latitude:  config.reportedLat !== null ? String(config.reportedLat) : '',
            longitude: config.reportedLng !== null ? String(config.reportedLng) : '',
            source:    config.reportedLat !== null ? 'farmer' : 'none',

            locating: false,
            locationError: '',
            map: null,
            marker: null,

            farmerEstimate: config.farmerEstimate,
            notesGap: config.notesGap,

            // Checkbox values come through as strings, so this array is
            // kept as strings too rather than mixing types with x-model.
            disasterIds: config.initialDisasterIds,
            disasterLabels: config.disasterLabels,

            init() {
                this.$nextTick(() => this.buildMap());

                // A tablet being rotated should switch layout, not get stuck
                // showing step 1 of a wizard on a wide screen.
                window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
                    this.wide = event.matches;
                    if (this.wide) this.$nextTick(() => this.map && this.map.invalidateSize());
                });
            },

            /* ==============================================================
             | Moving between steps
             ============================================================== */

            next() {
                const problem = this.problemWithStep(this.step);

                if (problem) {
                    this.stepError = problem;
                    return;
                }

                this.stepError = '';
                this.goTo(this.step + 1);
            },

            back() {
                this.stepError = '';
                this.goTo(this.step - 1);
            },

            goTo(step) {
                this.step = Math.min(Math.max(step, 1), this.lastStep);

                // Leaflet measures the container when it is created. If that
                // happened while step 3 was hidden the map comes out zero
                // sized, so it is told to re-measure on the way in.
                if (this.step === 3) {
                    this.$nextTick(() => this.map && this.map.invalidateSize());
                }

                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            /**
             * What, if anything, is stopping this step from being finished.
             * Returns an empty string when the step is fine.
             */
            problemWithStep(step) {
                if (step === 1 && ! this.shots.wide) {
                    return 'Please take the wide shot before continuing.';
                }

                if (step === 2) {
                    if (! this.severity) {
                        return 'Please choose the damage severity.';
                    }
                    if (! this.bandMatches()) {
                        return 'The percentage does not match the severity you chose.';
                    }
                    if (this.notesRequired() && ! this.notes.trim()) {
                        return 'Please write a note explaining the difference from the farmer\'s estimate.';
                    }
                }

                if (step === 3 && (! this.latitude || ! this.longitude)) {
                    return 'Please set the verified farm location.';
                }

                return '';
            },

            isComplete() {
                return ! this.problemWithStep(1)
                    && ! this.problemWithStep(2)
                    && ! this.problemWithStep(3);
            },

            whatIsMissing() {
                return this.problemWithStep(1)
                    || this.problemWithStep(2)
                    || this.problemWithStep(3);
            },

            /* ==============================================================
             | Severity and percentage have to agree (section 44)
             ============================================================== */

            bands: {
                slight:   [1, 25],
                moderate: [26, 50],
                partial:  [51, 99],
                total:    [100, 100],
            },

            labels: {
                slight: 'Slight', moderate: 'Moderate',
                partial: 'Partial', total: 'Total',
            },

            bandMatches() {
                if (! this.severity) {
                    return true;      // nothing chosen yet, nothing to complain about
                }

                const [min, max] = this.bands[this.severity];
                const value = parseInt(this.percent);

                return value >= min && value <= max;
            },

            bandHint() {
                if (! this.severity) return '';

                const [min, max] = this.bands[this.severity];

                return min === max
                    ? this.labels[this.severity] + ' means exactly ' + min + '%.'
                    : this.labels[this.severity] + ' means ' + min + ' to ' + max + '%.';
            },

            bandRange() {
                if (! this.severity) return '';

                const [min, max] = this.bands[this.severity];
                return min === max ? min + '%' : min + ' - ' + max + '%';
            },

            severityLabel() {
                return this.labels[this.severity] || 'Not chosen';
            },

            /* ==============================================================
             | Notes (section 46)
             ============================================================== */

            gap() {
                return Math.round(Math.abs(this.farmerEstimate - parseInt(this.percent)));
            },

            /**
             * Notes are optional until the technician's figure is a long way
             * from the farmer's. At that point the office is deciding
             * assistance on a smaller number than the farmer claimed, and
             * "the technician said so" is not enough to show them.
             */
            notesRequired() {
                return this.gap() >= this.notesGap;
            },

            /* ==============================================================
             | Disaster event (optional - add or correct anytime)
             ============================================================== */

            disasterSummary() {
                if (! this.disasterIds.length) {
                    return 'None linked';
                }

                return this.disasterIds
                    .map(id => this.disasterLabels[id] || this.disasterLabels[String(id)])
                    .filter(Boolean)
                    .join(', ') || 'None linked';
            },

            /* ==============================================================
             | Photos (section 43)
             ============================================================== */

            onShot(key, event) {
                const file = event.target.files[0];

                if (this.shots[key]) {
                    URL.revokeObjectURL(this.shots[key]);
                }

                this.shots[key] = file ? URL.createObjectURL(file) : null;
                this.stepError = '';
            },

            clearShot(key, input) {
                if (this.shots[key]) {
                    URL.revokeObjectURL(this.shots[key]);
                }

                this.shots[key] = null;

                // The preview and the input have to be cleared together, or
                // the old file is still what gets uploaded.
                if (input) input.value = '';
            },

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

            photoSummary() {
                const parts = [];

                if (this.shots.wide) parts.push('wide shot');
                if (this.shots.closeup) parts.push('close-up');
                if (this.previews.length) parts.push(this.previews.length + ' extra');

                return parts.length ? parts.join(', ') : 'None taken yet';
            },

            /* ==============================================================
             | Location (sections 47 to 49)
             ============================================================== */

            buildMap() {
                const el = document.getElementById('verifyMap');
                if (! el) return;

                // Tanza town centre, used only until we have a real pin.
                const startLat = parseFloat(this.latitude) || 14.3947;
                const startLng = parseFloat(this.longitude) || 120.8519;

                this.map = L.map(el).setView([startLat, startLng], this.latitude ? 16 : 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(this.map);

                this.marker = L.marker([startLat, startLng], { draggable: true }).addTo(this.map);

                this.marker.on('dragend', () => {
                    const p = this.marker.getLatLng();
                    this.setCoords(p.lat, p.lng, 'manual');
                });

                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    this.setCoords(e.latlng.lat, e.latlng.lng, 'manual');
                });
            },

            setCoords(lat, lng, source) {
                this.latitude = lat.toFixed(7);
                this.longitude = lng.toFixed(7);
                this.source = source;
                this.stepError = '';

                if (this.map && this.marker) {
                    this.marker.setLatLng([lat, lng]);
                    this.map.setView([lat, lng], Math.max(this.map.getZoom(), 16));
                }
            },

            captureLocation() {
                this.locationError = '';

                if (! navigator.geolocation) {
                    this.locationError = 'This device cannot give a location. Please set the pin on the map instead.';
                    return;
                }

                this.locating = true;

                navigator.geolocation.getCurrentPosition(
                    position => {
                        this.setCoords(position.coords.latitude, position.coords.longitude, 'gps');
                        this.locating = false;
                    },
                    error => {
                        this.locating = false;
                        this.locationError = error.code === error.PERMISSION_DENIED
                            ? 'Location permission was refused. Set the pin on the map or type the coordinates.'
                            : 'Your location could not be found. Set the pin on the map instead.';
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            },

            /* ==============================================================
             | The list shown in the confirmation dialog (section 91.15)
             |
             | Step 4 above is where you read your own answers back. This is
             | the last stop after that, so a mis-tap on Submit cannot
             | record an inspection on its own.
             ============================================================== */

            reviewJson() {
                return JSON.stringify([
                    { label: 'Report',              value: @js($report->reference) },
                    { label: 'Farmer',              value: @js($report->farmer?->full_name ?? 'Unknown') },
                    { label: 'Damage severity',     value: this.severityLabel() },
                    { label: "Farmer's estimate",   value: this.farmerEstimate + '%' },
                    { label: 'You assessed',        value: this.percent + '%' },
                    { label: 'Inspection photos',   value: this.photoSummary() },
                    { label: 'Disaster event',      value: this.disasterSummary() },
                    { label: 'Notes',               value: this.notes ? this.notes.slice(0, 90) + (this.notes.length > 90 ? '...' : '') : 'Not written' },
                    {
                        label: 'Verified location',
                        value: this.latitude && this.longitude
                            ? this.latitude + ', ' + this.longitude
                              + (this.source === 'gps' ? ' (GPS)' : this.source === 'farmer' ? ' (farmer\'s, unchanged)' : ' (set on map)')
                            : 'Not set',
                    },
                ]);
            },
        };
    }
</script>
@endpush
