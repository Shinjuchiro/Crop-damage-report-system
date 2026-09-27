@extends('layouts.app')

@section('title', 'Inspection Briefing')
@section('heading', 'Before you inspect ' . $report->reference)
@section('heading-fil', 'Bago ang Pagsusuri')
@section('subheading', 'What the farmer recorded planting, against what they are reporting damaged.')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('technician.validation.index')">Back to Validation</x-ui.button>
@endsection

@section('content')

{{--
    THE INSPECTION BRIEFING  (technician module revision, Sept 2026)

    Validation is where an inspection is recorded, and this is the page
    between the queue and the form. It exists so that nobody starts an
    inspection without first seeing the planted-against-damaged comparison:
    a technician who has read it knows which figure to measure before they
    get out of the truck.

    Start Inspection is here and nowhere else. Assigned Reports used to
    carry it too, which meant the comparison could be skipped entirely.

    Everything the farmer submitted stays read only (proposal section 40).
    The comparison judges nothing (section 62): it is a briefing, and a
    crop with no planting record on file is still fully available to
    inspect.
--}}

@php
    $started  = $report->status === 'under_verification';
    $barangay = $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name;
    $contact  = $report->farmer?->user?->phone_number;
    $ha       = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' ha';
@endphp

<div class="space-y-4">

    {{-- ============================================================
         WHO AND WHERE
         The first thing a technician needs is the name to ask for and
         the number to ring if they cannot find the place. The call
         button is a real tel: link, sized for a thumb.
    ============================================================= --}}
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xl font-bold leading-tight text-foreground">
                    {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                </p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ $report->farmer?->association?->name ?? 'No association' }}
                </p>
            </div>
            <x-ui.status :value="$report->status" />
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-muted-foreground">Barangay</dt>
                <dd class="font-semibold text-foreground">{{ $barangay ?: 'Not set' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Farm size on file</dt>
                <dd class="font-semibold text-foreground">{{ $ha($report->farmer?->farm_size_hectares) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Reported</dt>
                <dd class="font-semibold text-foreground">{{ $report->created_at?->format('M d, Y') ?? '-' }}</dd>
            </div>
        </dl>

        @if ($contact)
            <a href="tel:{{ preg_replace('/\s+/', '', $contact) }}"
               class="mt-4 flex h-12 items-center justify-center gap-2 rounded-lg bg-primary px-4
                      text-sm font-semibold text-primary-foreground shadow-sm transition active:scale-95 sm:w-auto sm:self-start">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 1.9.6 2.8a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.3-1.1a2 2 0 012.1-.5c.9.3 1.8.5 2.8.6a2 2 0 011.7 2z"/>
                </svg>
                Call {{ $contact }}
            </a>
        @endif
    </x-ui.card>

    {{-- ============================================================
         THE COMPARISON. The reason this page exists.
    ============================================================= --}}
    <x-ui.card title="Planted against damaged"
               description="The farmer's own planting records, set beside what this report claims.">
        @include('technician.partials.planting-comparison', ['comparison' => $comparison])
    </x-ui.card>

    {{-- ============================================================
         THE REST OF WHAT THE FARMER SENT
         Summarised, not repeated in full: the complete read only report
         is one link away and this page should stay short enough to read
         standing up.
    ============================================================= --}}
    <x-ui.card title="What else the farmer sent">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-muted-foreground">Disaster</dt>
                <dd class="font-semibold text-foreground">
                    {{ $report->disasters->map(fn ($d) => $d->name)->join(', ') ?: 'Not recorded' }}
                </dd>
            </div>

            <div>
                <dt class="text-xs text-muted-foreground">Photos of the damage</dt>
                <dd class="font-semibold text-foreground">
                    {{ $report->photos->count() }}
                    {{ $report->photos->count() === 1 ? 'photo' : 'photos' }} attached
                </dd>
            </div>

            <div>
                <dt class="text-xs text-muted-foreground">Location the farmer gave</dt>
                <dd class="font-semibold text-foreground">
                    @if ($report->reported_latitude && $report->reported_longitude)
                        {{ number_format((float) $report->reported_latitude, 5) }},
                        {{ number_format((float) $report->reported_longitude, 5) }}
                    @else
                        No coordinates. You will need to pin the farm yourself.
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs text-muted-foreground">Farmer's remarks</dt>
                <dd class="text-foreground">{{ $report->description ?: 'None written.' }}</dd>
            </div>
        </dl>

        <x-slot:footer>
            <a href="{{ route('technician.reports.show', $report) }}"
               class="font-medium text-primary hover:underline">
                Read the full report, photos included
            </a>
        </x-slot:footer>
    </x-ui.card>

    {{-- ============================================================
         THE ACTION (proposal sections 41 and 91)
         Never a bare button: Start Inspection goes through the
         confirmation dialog, because a technician who taps it by
         accident from the car park has just told the office they are
         standing in the field.
    ============================================================= --}}
    <x-ui.card>
        @if ($started)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-base font-semibold text-foreground">Your inspection is in progress</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Started {{ $report->validation?->inspection_started_at?->format('M d, Y g:i A') ?? 'earlier' }}.
                        Nothing is submitted until you confirm it at the end of the form.
                    </p>
                </div>

                <x-ui.button size="lg" class="shrink-0 w-full sm:w-auto"
                             :href="route('technician.inspection.edit', $report)">
                    Continue Inspection
                </x-ui.button>
            </div>
        @else
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-base font-semibold text-foreground">Ready to inspect this farm?</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Make sure you are at the reported farm location before starting.
                        <span class="block">Siguraduhin pong nasa sakahan na kayo bago simulan.</span>
                    </p>
                </div>

                <form method="POST" action="{{ route('technician.inspection.start', $report) }}"
                      class="shrink-0 w-full sm:w-auto"
                      data-confirm="You are about to begin the field inspection for this damage report. Please make sure you are at the reported farm location."
                      data-confirm-title="Start Inspection?"
                      data-confirm-action="Start Inspection">
                    @csrf
                    <x-ui.button size="lg" type="submit" class="w-full sm:w-auto">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 4l14 8-14 8V4z"/>
                        </svg>
                        Start Inspection
                    </x-ui.button>
                </form>
            </div>
        @endif
    </x-ui.card>
</div>
@endsection
