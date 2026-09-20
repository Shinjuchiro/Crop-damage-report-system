@extends('layouts.app')

@section('title', 'My Reports')
@section('heading', 'My Damage Reports')
@section('subheading', 'Every report you have submitted and where it stands. Ang kalagayan ng inyong mga ulat.')

@section('header-actions')
    <x-ui.button size="lg" :href="route('farmer.reports.create')">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Report Damage
    </x-ui.button>
@endsection

@php
    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a report's View is followed)
         the detail panel full-screen with its own Back link. Cards rather
         than a table on the list side: a farmer reads one report at a time,
         and the status line is what they came to check. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }} space-y-4">

            @forelse ($reports as $report)
                <x-ui.card class="card-hover {{ $selected?->id === $report->id ? 'ring-2 ring-primary' : '' }}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-card-foreground">
                                    DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <x-ui.status :value="$report->status" />
                                @if ($report->validation?->severity)
                                    <x-ui.status :value="$report->validation->severity" />
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($report->crops as $crop)
                                    <x-ui.badge variant="primary">
                                        {{ $crop->crop_specify ?: $crop->crop?->name }}
                                        &middot; {{ number_format($crop->damaged_area_hectares, 2) }} ha
                                    </x-ui.badge>
                                @endforeach
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ $report->disasters->pluck('name')->join(', ') ?: 'No disaster recorded' }}
                                &middot; submitted {{ $report->created_at?->format('M d, Y') }}
                                @if ($report->photos_count)
                                    &middot; {{ $report->photos_count }}
                                    {{ \Illuminate\Support\Str::plural('photo', $report->photos_count) }}
                                @endif
                            </p>

                            {{-- The one line a farmer actually wants: what happens next --}}
                            <p class="text-sm font-medium text-foreground">
                                @switch($report->status)
                                    @case('pending')
                                        Waiting for the office to assign a technician.
                                        <span class="font-normal text-muted-foreground">Hinihintay ang technician.</span>
                                        @break
                                    @case('assigned')
                                        {{ $report->assignedTechnician?->display_name ?? 'A technician' }}
                                        will visit your farm.
                                        <span class="font-normal text-muted-foreground">May bibisitang technician.</span>
                                        @break
                                    @case('under_verification')
                                        The inspection is underway.
                                        <span class="font-normal text-muted-foreground">Isinasagawa na ang pagsusuri.</span>
                                        @break
                                    @case('verified')
                                        Inspected and verified. Waiting for the office's decision.
                                        @break
                                    @case('approved')
                                        Approved. Any assistance will appear under Assistance.
                                        @break
                                    @case('rejected')
                                        This report was not approved. Please contact the office.
                                        @break
                                    @case('flagged')
                                        The office has questions about this report.
                                        @break
                                @endswitch
                            </p>
                        </div>

                        <x-ui.button variant="view" class="shrink-0" :href="$viewUrl($report->id)">View</x-ui.button>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card :padded="false">
                    <x-ui.empty title="You have not reported any damage"
                                icon="M20 6L9 17l-5-5"
                                message="That is good news. If a typhoon, flood or drought damages your crops, report it here with photos so the office can verify it and send assistance.">
                        <x-ui.button size="lg" :href="route('farmer.reports.create')">Report Damage</x-ui.button>
                    </x-ui.empty>
                </x-ui.card>
            @endforelse

            @if ($reports->hasPages())
                <div class="pt-2">{{ $reports->links() }}</div>
            @endif
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a report from the list to view its details.">
            @if ($selected)
                @php
                    $report = $selected;
                    $order = ['pending', 'assigned', 'under_verification', 'verified', 'approved'];
                    $steps = [
                        ['Submitted', $report->created_at !== null],
                        ['Technician assigned', $report->assigned_technician_id !== null],
                        ['Field inspection', in_array($report->status, ['under_verification', 'verified', 'approved'], true)],
                        ['Verified', in_array($report->status, ['verified', 'approved'], true)],
                        ['Office decision', in_array($report->status, ['approved', 'rejected'], true)],
                    ];
                @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Report</p>
                    <h3 class="mt-0.5 text-lg font-bold text-foreground">
                        DR-{{ str_pad($report->id, 4, '0', STR_PAD_LEFT) }}
                    </h3>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <x-ui.status :value="$report->status" />
                        @if ($report->validation?->severity)
                            <x-ui.status :value="$report->validation->severity" />
                        @endif
                    </div>
                </div>

                {{-- Progress --}}
                <div class="border-t border-border pt-4">
                    <ol class="space-y-2">
                        @foreach ($steps as [$label, $done])
                            <li class="flex items-center gap-2 text-xs">
                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full
                                             {{ $done ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                                    @if ($done)
                                        <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="3"
                                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    @endif
                                </span>
                                <span class="{{ $done ? 'font-medium text-foreground' : 'text-muted-foreground' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Crops --}}
                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Damaged Crops</p>
                    <div class="space-y-2">
                        @foreach ($report->crops as $crop)
                            <div class="rounded-lg bg-muted/50 px-3 py-2 text-xs">
                                <p class="font-bold text-foreground">
                                    {{ $crop->crop?->name }}
                                    @if ($crop->crop_specify)
                                        <span class="font-normal text-muted-foreground">&middot; {{ $crop->crop_specify }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-muted-foreground">
                                    {{ number_format($crop->damaged_area_hectares, 2) }} ha
                                    &middot; planted {{ $crop->date_planted?->format('M d, Y') }}
                                    &middot; {{ $crop->estimated_damage_percent }}% estimate
                                </p>
                                <p class="mt-0.5 text-muted-foreground">
                                    &#8369;{{ number_format($crop->production_cost, 2) }} cost
                                    &middot; &#8369;{{ number_format($crop->farmgate_price_per_kg, 2) }}/kg
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs font-medium text-foreground">
                        Total: {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                        &middot; est. &#8369;{{ number_format($report->crops->sum('total_damage_cost'), 2) }}
                    </p>
                </div>

                {{-- Cause / disaster --}}
                <div class="border-t border-border pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cause</p>
                    <p class="mt-0.5 text-sm font-medium text-foreground">{{ $report->damage_cause_label }}</p>
                    @if ($report->disasters->isNotEmpty())
                        <div class="mt-2 space-y-1.5">
                            @foreach ($report->disasters as $disaster)
                                <div class="text-xs">
                                    <span class="font-medium text-foreground">{{ $disaster->name }}</span>
                                    <span class="block text-muted-foreground">
                                        {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                                        @if ($disaster->date_start)
                                            &middot; {{ $disaster->date_start->format('M d, Y') }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Location --}}
                <div class="border-t border-border pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Location as You Reported It</p>
                    <p class="mt-0.5 text-xs font-medium text-foreground">{{ $report->reportedBarangay?->name ?? 'Not set' }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">{{ $report->farm_location_description }}</p>
                </div>

                {{-- Remarks --}}
                @if ($report->description)
                    <div class="border-t border-border pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Your Remarks</p>
                        <p class="mt-0.5 whitespace-pre-line text-xs leading-relaxed text-foreground">{{ $report->description }}</p>
                    </div>
                @endif

                {{-- Photos --}}
                <div class="border-t border-border pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Your Photos ({{ $report->photos->count() }})
                    </p>
                    @if ($report->photos->isEmpty())
                        <p class="text-xs text-muted-foreground">No photos were attached to this report.</p>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($report->photos as $photo)
                                <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener"
                                   class="block aspect-square overflow-hidden rounded-lg border border-border">
                                    <img src="{{ Storage::url($photo->file_path) }}" alt="Damage photo"
                                         loading="lazy" class="h-full w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Technician inspection, once it exists. Read only. --}}
                @if ($report->validation)
                    <div class="border-t border-border pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Technician Inspection
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Inspected by {{ $report->validation->technician?->display_name ?? 'a technician' }}
                        </p>
                        <dl class="mt-2 space-y-1.5 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Severity</dt>
                                <dd><x-ui.status :value="$report->validation->severity" /></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Assessed damage</dt>
                                <dd class="font-medium text-foreground">{{ $report->validation->assessed_damage_percent }}%</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Inspected on</dt>
                                <dd class="font-medium text-foreground">
                                    {{ $report->validation->validated_at?->format('M d, Y') ?? 'In progress' }}
                                </dd>
                            </div>
                        </dl>
                        @if ($report->validation->notes)
                            <div class="mt-2 rounded-lg bg-muted px-3 py-2 text-xs">
                                <p class="font-medium uppercase tracking-wide text-muted-foreground">Notes</p>
                                <p class="mt-1 whitespace-pre-line leading-relaxed text-foreground">{{ $report->validation->notes }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                <p class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground">
                    A submitted report cannot be changed here, because it is the evidence the technician inspects.
                    If something is wrong, contact the Municipal Agriculture Office.
                </p>
            @endif
        </x-ui.detail-panel>
    </div>
@endsection
