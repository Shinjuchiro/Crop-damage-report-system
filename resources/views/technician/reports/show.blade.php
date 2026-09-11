@extends('layouts.app')

@section('title', 'Field Inspection & Validation')
@section('heading', 'Report ' . $report->reference)
@section('subheading', 'Submitted by ' . ($report->farmer?->full_name ?? 'a farmer') . ' on ' . $report->created_at?->format('F d, Y'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('technician.dashboard')">Back to assignments</x-ui.button>
@endsection

@section('content')

{{--
    Proposal section 40: the complete farmer-submitted report.

    Everything on this page is READ ONLY. The technician reviews what the
    farmer sent, then records their own findings separately. Nothing here
    can be edited, which is the whole point: the farmer's report is the
    evidence being inspected.

    Two layouts share this one file:

      Phone   a short "Assigned Inspection" card with only what you need
              standing in a field, and a View Report Details button that
              opens the rest. This follows the approved mockup.

      Laptop  everything expanded, because there is room for it and the
              office reads these at a desk.
--}}

@php
    $barangay = $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? 'Location not set';
    $contact  = $report->farmer?->user?->phone_number;
@endphp

{{--
    "wide" tracks whether we are on a laptop sized screen. The full report
    below is always open there and collapsible on a phone, and doing that
    with x-show rather than Tailwind's hidden/lg:block avoids the two rules
    fighting over the same element when the toggle is pressed.
--}}
<div x-data="{
        showDetails: false,
        wide: window.matchMedia('(min-width: 1024px)').matches,
        init() {
            window.matchMedia('(min-width: 1024px)')
                  .addEventListener('change', e => this.wide = e.matches);
        }
     }"
     class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    {{-- =================================================================
         PHONE: ASSIGNED INSPECTION CARD
         Four facts, a way to see the rest, how to reach the farmer, and
         the button that starts the work. Nothing else, because this is
         read while standing at the side of a road.
    ================================================================== --}}
    <div class="lg:hidden">

        <p class="mb-2 text-sm font-semibold">Assigned Inspection</p>

        <x-ui.card>
            <div class="flex items-start justify-between gap-3">
                <p class="text-base font-bold">{{ $report->reference }}</p>
                <x-ui.status :value="$report->status" />
            </div>

            <dl class="mt-4 divide-y divide-border">
                @php
                    /* Icon, label, value. Built as a list so the four rows
                       stay identical in spacing and nobody has to repeat the
                       same markup four times. */
                    $rows = [
                        ['M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9',
                         'Farmer', $report->farmer?->full_name ?? 'Unknown farmer'],

                        ['M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11zM12 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
                         'Barangay', $barangay],

                        ['M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
                         'Crops', $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->join(', ') ?: 'Not set'],

                        ['M3 10h18M8 3v4M16 3v4M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
                         'Reported', $report->created_at?->format('F d, Y')],
                    ];
                @endphp

                @foreach ($rows as [$icon, $label, $value])
                    <div class="flex items-center gap-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                     bg-accent text-primary" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="{{ $icon }}"/>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                            <dd class="truncate text-sm font-medium">{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>

            <x-ui.button size="lg" class="mt-4 w-full" @click="showDetails = ! showDetails">
                <span x-show="! showDetails">View Report Details</span>
                <span x-show="showDetails" x-cloak>Hide Report Details</span>
            </x-ui.button>
        </x-ui.card>

        {{-- Inspection information. Plain instructions, in both languages,
             because this is the part a new technician actually needs. --}}
        <div class="mt-4">
            <p class="mb-2 text-sm font-semibold">Inspection Information</p>

            <div class="flex items-start gap-3 rounded-xl border border-primary/30 bg-accent px-4 py-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4"/>
                </svg>
                <p class="text-sm leading-relaxed text-accent-foreground">
                    Go to the reported location to validate the crop damage.
                    <span class="mt-0.5 block text-xs opacity-80">
                        Pumunta po sa nakasaad na lokasyon upang suriin ang pinsala.
                    </span>
                </p>
            </div>
        </div>

        {{-- Farmer contact. A tap dials, because the alternative is copying
             a number by hand while holding a phone in the sun. --}}
        <div class="mt-4">
            <p class="mb-2 text-sm font-semibold">Farmer Contact</p>

            <div class="flex items-center gap-2">
                <div class="flex h-12 min-w-0 flex-1 items-center gap-2 rounded-md border border-input
                            bg-card px-3 shadow-sm">
                    <svg class="h-4 w-4 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 1.9.6 2.8a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.3-1.1a2 2 0 012.1-.5c.9.3 1.8.5 2.8.6a2 2 0 011.7 2z"/>
                    </svg>
                    <span class="truncate text-sm font-medium">{{ $contact ?: 'No number on file' }}</span>
                </div>

                @if ($contact)
                    <a href="tel:{{ preg_replace('/\s+/', '', $contact) }}"
                       aria-label="Call {{ $report->farmer?->full_name }}"
                       class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-primary
                              text-primary-foreground shadow-sm transition active:scale-95">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 1.9.6 2.8a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.3-1.1a2 2 0 012.1-.5c.9.3 1.8.5 2.8.6a2 2 0 011.7 2z"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- The action, right at the bottom where a thumb rests --}}
        @if ($report->status === 'assigned')
            <form method="POST" action="{{ route('technician.inspection.start', $report) }}" class="mt-4"
                  data-confirm="You are about to begin the field inspection for this damage report. Please make sure you are at the reported farm location."
                  data-confirm-title="Start Inspection?"
                  data-confirm-action="Start Inspection">
                @csrf
                <x-ui.button size="lg" type="submit" class="w-full">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                         stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 4l14 8-14 8V4z"/></svg>
                    Start Inspection
                </x-ui.button>
            </form>
        @elseif ($report->status === 'under_verification')
            <x-ui.button size="lg" class="mt-4 w-full"
                         :href="route('technician.inspection.edit', $report)">
                Continue Inspection
            </x-ui.button>
        @endif
    </div>

    {{-- =================================================================
         THE FULL REPORT
         Always visible on a laptop. On a phone it stays closed until
         "View Report Details" is pressed.
    ================================================================== --}}
    <div x-show="showDetails || wide" x-cloak class="space-y-4">

        {{-- Status --}}
        <x-ui.card class="hidden lg:block">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status :value="$report->status" />
                @if ($report->validation?->severity)
                    <x-ui.status :value="$report->validation->severity" />
                @endif

                @if ($report->validation?->inspection_started_at)
                    <span class="text-xs text-muted-foreground">
                        Inspection started
                        {{ $report->validation->inspection_started_at->format('M d, Y g:i A') }}
                    </span>
                @endif
            </div>
        </x-ui.card>

        {{-- Farmer and farm --}}
        <x-ui.card title="Farmer and Farm / Magsasaka at Sakahan">
            @php
                $details = [
                    'Farmer'      => $report->farmer?->full_name ?? 'Unknown',
                    'Association' => $report->farmer?->association?->name ?? 'Not set',
                    'Contact'     => $contact ?: 'Not provided',
                    'Barangay'    => $barangay,
                    'Ownership'   => $report->farmer?->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                    'Farm Size'   => $report->farmer?->farm_size_hectares
                                      ? number_format($report->farmer->farm_size_hectares, 2) . ' ha' : 'Not set',
                ];
            @endphp

            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                @foreach ($details as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        {{-- What the farmer reported --}}
        <x-ui.card title="Damage reported by the farmer" :padded="false"
                   description="These are the farmer's own figures. Your assessment is recorded separately.">
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th>Crop</th>
                        <th>Damaged Area</th>
                        <th>Date Planted</th>
                        <th>Farmer Estimate</th>
                        <th>Production Cost</th>
                        <th>Farmgate Price</th>
                    </tr>
                </x-slot:head>

                @foreach ($report->crops as $crop)
                    <tr>
                        <td class="font-medium">
                            {{ $crop->crop?->name }}
                            @if ($crop->crop_specify)
                                <span class="block text-xs text-muted-foreground">{{ $crop->crop_specify }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($crop->damaged_area_hectares, 2) }} ha</td>
                        <td class="text-muted-foreground">{{ $crop->date_planted?->format('M d, Y') }}</td>
                        <td class="font-semibold">{{ $crop->estimated_damage_percent }}%</td>
                        <td>&#8369;{{ number_format($crop->production_cost, 2) }}</td>
                        <td>&#8369;{{ number_format($crop->farmgate_price_per_kg, 2) }}/kg</td>
                    </tr>
                @endforeach

                <x-slot:foot>
                    <tr>
                        <td class="font-semibold">Total</td>
                        <td class="font-semibold">
                            {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </x-slot:foot>
            </x-ui.table>
        </x-ui.card>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-ui.card title="What caused the damage">
                {{-- The cause is on every report. The declared event underneath
                     is only there when the office had recorded one, so a pest
                     or heat report shows a cause and no event. That is correct,
                     not missing data. --}}
                <div class="mb-4 rounded-lg bg-muted px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cause</p>
                    <p class="mt-0.5 text-base font-semibold">{{ $report->damage_cause_label }}</p>
                </div>

                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Declared event
                </p>

                @if ($report->disasters->isEmpty())
                    <p class="text-sm text-muted-foreground">Not linked to a declared event.</p>
                @else
                    <ul class="space-y-2.5">
                        @foreach ($report->disasters as $disaster)
                            <li>
                                <p class="text-sm font-medium">{{ $disaster->name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                                    @if ($disaster->date_start)
                                        &middot; {{ $disaster->date_start->format('M d, Y') }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- Section 37: where the farmer says the farm is. This is what
                 you are going out to check, so it is worth reading carefully. --}}
            <x-ui.card title="Location as the farmer reported it">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">How to find it</dt>
                        <dd class="mt-0.5 leading-relaxed">{{ $report->farm_location_description }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Coordinates</dt>
                        <dd class="mt-0.5 font-medium">
                            @if ($report->has_reported_coordinates)
                                {{ $report->reported_latitude }}, {{ $report->reported_longitude }}
                                <span class="text-xs font-normal text-muted-foreground">
                                    ({{ $report->location_source === 'gps' ? 'from GPS' : 'typed in' }})
                                </span>

                                {{-- Opens the phone's own map app with directions.
                                     The technician has to actually get there. --}}
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $report->reported_latitude }},{{ $report->reported_longitude }}"
                                   target="_blank" rel="noopener"
                                   class="mt-1 block text-xs font-medium text-primary hover:underline">
                                    Open directions
                                </a>
                            @else
                                Not provided. Use the written description above.
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        @if ($report->description)
            <x-ui.card title="Farmer's remarks">
                <p class="whitespace-pre-line text-sm leading-relaxed">{{ $report->description }}</p>
            </x-ui.card>
        @endif

        {{-- Section 40: the farmer's photos --}}
        <x-ui.card title="Farmer's photos" :description="$report->photos->count() . ' uploaded'">
            @if ($report->photos->isEmpty())
                <p class="text-sm text-muted-foreground">The farmer did not attach any photos.</p>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-6">
                    @foreach ($report->photos as $photo)
                        <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener"
                           class="aspect-square overflow-hidden rounded-lg border border-border">
                            <img src="{{ Storage::url($photo->file_path) }}" alt="Farmer photo {{ $loop->iteration }}"
                                 loading="lazy" class="h-full w-full object-cover">
                        </a>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        {{-- Your inspection, once it exists --}}
        @if ($report->validation?->validated_at)
            <x-ui.card title="Your inspection"
                       :description="'Submitted ' . $report->validation->validated_at->format('F d, Y g:i A')">
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Severity</dt>
                        <dd class="mt-1"><x-ui.status :value="$report->validation->severity" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assessed damage</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $report->validation->assessed_damage_percent }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Verified location</dt>
                        <dd class="mt-1 text-sm font-medium">
                            {{ $report->validation->latitude }}, {{ $report->validation->longitude }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Photos</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $report->validation->photos->count() }}</dd>
                    </div>
                </dl>

                @if ($report->validation->photos->isNotEmpty())
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-6">
                        @foreach ($report->validation->photos as $photo)
                            <figure>
                                <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener"
                                   class="block aspect-square overflow-hidden rounded-lg border border-border">
                                    <img src="{{ Storage::url($photo->file_path) }}"
                                         alt="{{ $photo->shot_label }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                </a>
                                {{-- Labelled, so months from now it is still clear
                                     which one was the wide shot. --}}
                                <figcaption class="mt-1 text-xs text-muted-foreground">
                                    {{ $photo->shot_label }}
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif

                @if ($report->validation->notes)
                    <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Inspection notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $report->validation->notes }}</p>
                    </div>
                @endif
            </x-ui.card>
        @endif
    </div>

    {{-- =================================================================
         DESKTOP CTA (section 41)
         The phone has its own Start Inspection button up in the card, so
         this block is laptop only.
    ================================================================== --}}
    @if ($report->status === 'assigned')
        <x-ui.card class="hidden lg:block">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-base font-semibold">Ready to inspect this farm?</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Make sure you are at the reported farm location before starting.
                        <span class="block">Siguraduhin pong nasa sakahan na kayo bago simulan.</span>
                    </p>
                </div>

                <form method="POST" action="{{ route('technician.inspection.start', $report) }}" class="shrink-0"
                      data-confirm="You are about to begin the field inspection for this damage report. Please make sure you are at the reported farm location."
                      data-confirm-title="Start Inspection?"
                      data-confirm-action="Start Inspection">
                    @csrf
                    <x-ui.button size="lg" type="submit" class="w-full sm:w-auto">Start Inspection</x-ui.button>
                </form>
            </div>
        </x-ui.card>

    @elseif ($report->status === 'under_verification')
        <x-ui.card class="hidden lg:block">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-muted-foreground">
                    Your inspection is in progress and has not been submitted yet.
                </p>
                <x-ui.button size="lg" class="shrink-0"
                             :href="route('technician.inspection.edit', $report)">
                    Continue Inspection
                </x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
@endsection
