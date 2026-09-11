@extends('layouts.app')

@section('title', 'Crop Planting Monitoring')

@section('content')

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Planting Submissions" :value="number_format($summary['records'])" />
        <x-ui.stat label="Submitted This Month" :value="number_format($summary['this_month'])" />
        <x-ui.stat label="Farmers Reporting" :value="number_format($summary['farmers'])"
                     hint="Farmers with at least one record" />
        <x-ui.stat label="Total Area Planted" :value="number_format($summary['area_planted'], 2)" suffix="ha" />
    </div>

<div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <select name="association_id"
                class="min-w-56 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Association</option>
            @foreach ($associations as $association)
                <option value="{{ $association->id }}" @selected(request('association_id') == $association->id)>
                    {{ $association->name }}
                </option>
            @endforeach
        </select>

        <select name="crop_id"
                class="min-w-40 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Crops</option>
            @foreach ($crops as $crop)
                <option value="{{ $crop->id }}" @selected(request('crop_id') == $crop->id)>{{ $crop->name }}</option>
            @endforeach
        </select>

        <select name="barangay_id"
                class="min-w-44 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Barangay</option>
            @foreach ($barangays as $barangay)
                <option value="{{ $barangay->id }}" @selected(request('barangay_id') == $barangay->id)>
                    {{ $barangay->name }}
                </option>
            @endforeach
        </select>

        <div class="ml-auto flex items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer..."
                   class="w-56 rounded-lg border-2 border-primary px-3 py-2.5 text-sm focus:outline-none">
            <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                Search
            </button>
            @if (request()->hasAny(['search', 'association_id', 'crop_id', 'barangay_id']))
                <a href="{{ route('mao.crop-planting.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                    <th class="px-3 py-3">Farmer ID</th>
                    <th class="px-3 py-3">Name</th>
                    <th class="px-3 py-3">Association</th>
                    <th class="px-3 py-3">Crop</th>
                    <th class="px-3 py-3">Planting Date</th>
                    <th class="px-3 py-3">Farm Area</th>
                    <th class="px-3 py-3 text-center">Status</th>
                    <th class="px-3 py-3 text-center">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($plantings as $planting)
                    @php $farmer = $planting->plantingRecord?->farmer; @endphp
                    <tr class="hover:bg-muted/60">
                        <td class="px-3 py-4 text-foreground">
                            {{ $farmer ? 'FARM-' . str_pad($farmer->id, 3, '0', STR_PAD_LEFT) : '-' }}
                        </td>
                        <td class="px-3 py-4 font-medium text-foreground">{{ $farmer?->full_name ?? 'Unknown' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $farmer?->association?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">
                            {{ $planting->crop?->name ?? '-' }}
                            @if ($planting->crop_specify)
                                <span class="block text-xs text-muted-foreground">{{ $planting->crop_specify }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $planting->date_planted?->format('M d, Y') }}</td>
                        <td class="px-3 py-4 tabular-nums text-muted-foreground">{{ $planting->area_hectares }} ha</td>
                        <td class="px-3 py-4 text-center">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer?->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                {{ $farmer?->activity_status === 'active' ? 'Active' : 'In-Active' }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-center">
                            <a href="{{ route('mao.crop-planting.show', $planting->crop_planting_record_id) }}"
                               class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No planting records yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Records appear here once farmers submit their crop planting activity.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $plantings->links() }}</div>
</div>
@endsection
