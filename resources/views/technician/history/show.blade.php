@extends('layouts.app')

@section('title', 'Inspection Record')
@section('heading', 'Inspection record for ' . ($report?->reference ?? 'this report'))
@section('heading-fil', 'Talaan ng Pagsusuri')
@section('subheading', 'Planted, reported, inspected. The whole chain in the order it happened.')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('technician.history.index')">Back to Inspection History</x-ui.button>
@endsection

@section('content')

{{--
    ONE COMPLETED INSPECTION, END TO END
    (technician module revision, Sept 2026)

    Inspection History used to be a list that opened the read only damage
    report, which answered "what did the farmer say" rather than "what did
    I do". This page answers the second question, by laying out the three
    stages in the order they happened:

        1  What the farmer recorded planting
        2  What the farmer then reported damaged
        3  What this technician found on site

    It is a record, so everything on it is read only, including the
    technician's own findings. An inspection that has been submitted is
    evidence the office acts on; it is not a draft to go back and tidy.

    The same planted-against-damaged comparison the technician read on the
    Validation briefing is rebuilt here, so the record shows what they were
    working from at the time, not only the conclusion they reached.
--}}

@php
    $farmerEstimate = $report?->crops->avg('estimated_damage_percent');
    $assessed       = $validation->assessed_damage_percent;
    $gap            = ($farmerEstimate !== null && $assessed !== null)
        ? round($assessed - $farmerEstimate)
        : null;

    $ha = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' ha';
@endphp

<div class="space-y-4">

    {{-- ============================================================
         THE OUTCOME, FIRST
         Anyone opening this record wants the answer before the
         working, so the two percentages and the severity go at the
         top. Section 45: the farmer's estimate is never overwritten,
         so the gap between the two stays readable forever.
    ============================================================= --}}
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xl font-bold leading-tight text-foreground">
                    {{ $report?->farmer?->full_name ?? 'Unknown farmer' }}
                </p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ $report?->reportedBarangay?->name ?? $report?->farmer?->barangay?->name ?? 'Location not set' }}
                    @if ($report?->farmer?->association)
                        &middot; {{ $report->farmer->association->name }}
                    @endif
                </p>
            </div>
            <x-ui.status :value="$validation->severity" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg border border-border px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Farmer estimated</p>
                <p class="mt-0.5 text-xl font-bold text-foreground">
                    {{ $farmerEstimate !== null ? round($farmerEstimate) . '%' : '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-border bg-accent px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-wide text-accent-foreground/70">You assessed</p>
                <p class="mt-0.5 text-xl font-bold text-accent-foreground">
                    {{ $assessed !== null ? round($assessed) . '%' : '-' }}
                </p>
            </div>

            <div class="rounded-lg border border-border px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Difference</p>
                <p class="mt-0.5 text-xl font-bold text-foreground">
                    {{ $gap === null ? '-' : ($gap > 0 ? '+' : '') . $gap . ' pts' }}
                </p>
            </div>

            <div class="rounded-lg border border-border px-3 py-2.5">
                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Submitted</p>
                <p class="mt-0.5 text-sm font-bold leading-tight text-foreground">
                    {{ $validation->validated_at?->format('M d, Y') ?? '-' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ $validation->validated_at?->format('g:i A') }}
                </p>
            </div>
        </div>
    </x-ui.card>

    {{-- ============================================================
         STAGE 1: PLANTED
    ============================================================= --}}
    <x-ui.card :padded="false">
        <div class="flex items-center gap-3 border-b border-border px-4 py-3 sm:px-5">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border
                         bg-muted text-xs font-bold text-muted-foreground">1</span>
            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-wide text-foreground">What was planted</p>
                <p class="text-xs text-muted-foreground">Recorded by the farmer before this report was filed.</p>
            </div>
        </div>

        @if ($planting->isEmpty())
            <p class="px-4 py-5 text-sm text-muted-foreground sm:px-5">
                No planting records on file for this farmer before the report date. That does not
                invalidate anything: farmers are not required to record a planting before reporting
                damage, and section 62 treats this as a missing signal, not a problem with the report.
            </p>
        @else
            <ul class="divide-y divide-border">
                @foreach ($planting as $record)
                    <li class="px-4 py-3 sm:px-5">
                        <p class="text-xs text-muted-foreground">
                            Filed {{ $record->date_submitted?->format('M d, Y') ?? 'date not set' }}
                        </p>

                        <ul class="mt-1.5 space-y-1">
                            @foreach ($record->crops as $crop)
                                <li class="flex flex-wrap items-baseline gap-x-2 text-sm">
                                    <span class="font-semibold text-foreground">
                                        {{ $crop->crop_specify ?: $crop->crop?->name ?? 'Unnamed crop' }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ $ha($crop->area_hectares) }}
                                        &middot; planted {{ $crop->date_planted?->format('M d, Y') ?? 'date not set' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- ============================================================
         STAGE 2: REPORTED
         The comparison carries this stage, because what matters in a
         record is not the damage figures on their own but how they
         sat against the planting records at the time.
    ============================================================= --}}
    <x-ui.card :padded="false">
        <div class="flex items-center gap-3 border-b border-border px-4 py-3 sm:px-5">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border
                         bg-muted text-xs font-bold text-muted-foreground">2</span>
            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-wide text-foreground">What the farmer reported</p>
                <p class="text-xs text-muted-foreground">
                    Filed {{ $report?->created_at?->format('M d, Y') ?? 'date not set' }}, set against the records above.
                </p>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            @include('technician.partials.planting-comparison', ['comparison' => $comparison])

            <dl class="mt-4 grid gap-4 border-t border-border pt-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground">Disaster</dt>
                    <dd class="font-semibold text-foreground">
                        {{ $report?->disasters->map(fn ($d) => $d->name)->join(', ') ?: 'Not recorded' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Farmer's photos</dt>
                    <dd class="font-semibold text-foreground">{{ $report?->photos->count() ?? 0 }}</dd>
                </div>
                @if ($report?->description)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">Farmer's remarks</dt>
                        <dd class="whitespace-pre-line text-foreground">{{ $report->description }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($report)
            <x-slot:footer>
                <a href="{{ route('technician.reports.show', $report) }}"
                   class="font-medium text-primary hover:underline">
                    Read the full report, photos included
                </a>
            </x-slot:footer>
        @endif
    </x-ui.card>

    {{-- ============================================================
         STAGE 3: INSPECTED
    ============================================================= --}}
    <x-ui.card :padded="false">
        <div class="flex items-center gap-3 border-b border-border px-4 py-3 sm:px-5">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary
                         text-xs font-bold text-primary-foreground">3</span>
            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-wide text-foreground">What you found on site</p>
                <p class="text-xs text-muted-foreground">
                    Started {{ $validation->inspection_started_at?->format('M d, Y g:i A') ?? 'not recorded' }}.
                </p>
            </div>
        </div>

        <div class="space-y-4 p-4 sm:p-5">

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground">Severity you assessed</dt>
                    <dd class="mt-0.5"><x-ui.status :value="$validation->severity" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Verified location</dt>
                    <dd class="font-semibold text-foreground">
                        @if ($validation->latitude && $validation->longitude)
                            {{ number_format((float) $validation->latitude, 5) }},
                            {{ number_format((float) $validation->longitude, 5) }}
                        @else
                            Not pinned
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($validation->notes)
                <div class="rounded-lg border border-border bg-muted/40 px-3.5 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Your notes</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-foreground">
                        {{ $validation->notes }}
                    </p>
                </div>
            @endif

            @if ($validation->photos->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Your inspection photos ({{ $validation->photos->count() }})
                    </p>

                    {{-- Kept separate from the farmer's photos throughout
                         (proposal section 36): two different people
                         photographing the same field at two different
                         times are two different pieces of evidence. --}}
                    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($validation->photos as $photo)
                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" rel="noopener"
                               class="block overflow-hidden rounded-lg border border-border">
                                <img src="{{ asset('storage/' . $photo->file_path) }}"
                                     alt="Inspection photo {{ $loop->iteration }}"
                                     loading="lazy"
                                     class="h-24 w-full object-cover transition hover:opacity-90">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui.card>
</div>
@endsection
