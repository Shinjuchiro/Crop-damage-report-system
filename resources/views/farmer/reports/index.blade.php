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

@section('content')
<div class="space-y-4">

    @forelse ($reports as $report)
        {{-- Cards rather than a table. A farmer on a phone reads one report at
             a time, and the status line is what they came to check. --}}
        <x-ui.card class="card-hover">
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

                <x-ui.button variant="view" class="shrink-0"
                             :href="route('farmer.reports.show', $report)">View</x-ui.button>
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
@endsection
