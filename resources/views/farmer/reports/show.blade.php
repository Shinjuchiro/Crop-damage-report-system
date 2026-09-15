@extends('layouts.app')

@section('title', 'Damage Report')
@section('heading', 'Report DR-' . str_pad($report->id, 4, '0', STR_PAD_LEFT))
@section('subheading', 'Submitted ' . $report->created_at?->format('F d, Y'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('farmer.reports.index')">Back to my reports</x-ui.button>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Status and what happens next --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status :value="$report->status" />
            @if ($report->validation?->severity)
                <x-ui.status :value="$report->validation->severity" />
            @endif
        </div>

        <ol class="mt-5 space-y-3">
            @php
                // A plain progress list rather than a chart. The farmer needs to
                // know where their report is, not to admire a timeline.
                $order = ['pending', 'assigned', 'under_verification', 'verified', 'approved'];
                $currentIndex = array_search($report->status, $order, true);

                $steps = [
                    ['Submitted', 'Naisumite na', true],
                    ['Technician assigned', 'May naka-atas na technician', $report->assigned_technician_id !== null],
                    ['Field inspection', 'Pagsusuri sa sakahan', in_array($report->status, ['under_verification', 'verified', 'approved'], true)],
                    ['Verified', 'Nasuri na', in_array($report->status, ['verified', 'approved'], true)],
                    ['Office decision', 'Desisyon ng tanggapan', in_array($report->status, ['approved', 'rejected'], true)],
                ];
            @endphp

            @foreach ($steps as [$label, $filipino, $done])
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full
                                 {{ $done ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        @if ($done)
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        @else
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        @endif
                    </span>

                    <span class="min-w-0">
                        <span class="block text-sm {{ $done ? 'font-medium text-card-foreground' : 'text-muted-foreground' }}">
                            {{ $label }}
                        </span>
                        <span class="block text-xs text-muted-foreground">{{ $filipino }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </x-ui.card>

    {{-- Crops --}}
    <x-ui.card title="Damaged crops" :padded="false">
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th>Crop</th>
                    <th>Damaged Area</th>
                    <th>Planted</th>
                    <th>Your Estimate</th>
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
                    <td>{{ $crop->estimated_damage_percent }}%</td>
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
                    <td colspan="4" class="text-muted-foreground">
                        Estimated damage cost
                        <span class="font-semibold text-foreground">
                            &#8369;{{ number_format($report->crops->sum('total_damage_cost'), 2) }}
                        </span>
                    </td>
                </tr>
            </x-slot:foot>
        </x-ui.table>
    </x-ui.card>

    {{-- Disasters and remarks --}}
    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card title="What caused the damage">
            {{-- The cause is always recorded. A linked event is only there when
                 the office had declared one, so it is shown underneath. --}}
            <div class="mb-4 rounded-lg bg-muted px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cause</p>
                <p class="mt-0.5 text-base font-semibold">{{ $report->damage_cause_label }}</p>
            </div>

            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Declared event
            </p>

            @if ($report->disasters->isEmpty())
                <p class="text-sm text-muted-foreground">
                    Not linked to a declared event. That is normal for pests, plant disease and heat damage.
                </p>
            @else
                <ul class="space-y-2.5">
                    @foreach ($report->disasters as $disaster)
                        <li>
                            <p class="text-sm font-medium text-card-foreground">{{ $disaster->name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ ucwords(str_replace('_', ' ', $disaster->type)) }}
                                @if ($disaster->date_start)
                                    &middot; {{ $disaster->date_start->format('M d, Y') }}
                                    @if ($disaster->date_end) to {{ $disaster->date_end->format('M d, Y') }} @endif
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Location as you reported it">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Barangay</dt>
                    <dd class="mt-0.5 font-medium">{{ $report->reportedBarangay?->name ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Description</dt>
                    <dd class="mt-0.5 leading-relaxed">{{ $report->farm_location_description }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    @if ($report->description)
        <x-ui.card title="Your remarks">
            <p class="whitespace-pre-line text-sm leading-relaxed">{{ $report->description }}</p>
        </x-ui.card>
    @endif

    {{-- Farmer photos --}}
    <x-ui.card title="Your photos" :description="$report->photos->count() . ' uploaded'">
        @if ($report->photos->isEmpty())
            <p class="text-sm text-muted-foreground">No photos were attached to this report.</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($report->photos as $photo)
                    <a href="{{ Storage::url($photo->file_path) }}" target="_blank" rel="noopener"
                       class="aspect-square overflow-hidden rounded-lg border border-border">
                        <img src="{{ Storage::url($photo->file_path) }}"
                             alt="Damage photo {{ $loop->iteration }}"
                             loading="lazy" class="h-full w-full object-cover">
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- The inspection, once it exists. Read only: this is the technician's
         record, and the farmer's own figures above are never changed by it. --}}
    @if ($report->validation)
        <x-ui.card title="Technician inspection"
                   :description="'Inspected by ' . ($report->validation->technician?->display_name ?? 'a technician')">

            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Severity</dt>
                    <dd class="mt-1"><x-ui.status :value="$report->validation->severity" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assessed damage</dt>
                    <dd class="mt-0.5 text-sm font-medium">{{ $report->validation->assessed_damage_percent }}%</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Inspected on</dt>
                    <dd class="mt-0.5 text-sm font-medium">
                        {{ $report->validation->validated_at?->format('M d, Y') ?? 'In progress' }}
                    </dd>
                </div>
            </dl>

            @if ($report->validation->notes)
                <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Inspection notes</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ $report->validation->notes }}</p>
                </div>
            @endif
        </x-ui.card>
    @endif

    <p class="text-sm text-muted-foreground">
        A submitted report cannot be changed here, because it is the evidence the technician inspects.
        If something is wrong, contact the Municipal Agriculture Office.
        <span class="mt-1 block">Kung may mali po, makipag-ugnayan sa tanggapan ng MAO.</span>
    </p>
</div>
@endsection
