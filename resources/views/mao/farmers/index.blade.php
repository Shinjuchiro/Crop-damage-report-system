@extends('layouts.app')

@section('title', "Farmers Association Management")

@section('content')
<div class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    {{-- Title + actions --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
        </div>

        <a href="{{ route('mao.associations.index') }}"
           class="shrink-0 rounded-lg bg-primary px-5 py-2.5 text-center text-sm font-semibold text-white hover:brightness-110">
            Manage Associations
        </a>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <x-ui.stat label="Total Farmers" :value="number_format($summary['total'])" />
        <x-ui.stat label="Verified" :value="number_format($summary['verified'])" />
        <x-ui.stat label="Pending" :value="number_format($summary['pending'])"
                     :href="route('mao.membership-applications.index', ['status' => 'pending'])" />
        <x-ui.stat label="Active" :value="number_format($summary['active'])" hint="With recent activity" />
        <x-ui.stat label="Inactive" :value="number_format($summary['inactive'])" />
    </div>

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

        <select name="barangay_id"
                class="min-w-48 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Barangay</option>
            @foreach ($barangays as $barangay)
                <option value="{{ $barangay->id }}" @selected(request('barangay_id') == $barangay->id)>
                    {{ $barangay->name }}
                </option>
            @endforeach
        </select>

        <select name="activity_status"
                class="min-w-40 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">Any Status</option>
            <option value="active" @selected(request('activity_status') === 'active')>Active</option>
            <option value="inactive" @selected(request('activity_status') === 'inactive')>In-Active</option>
        </select>

        <div class="ml-auto flex items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                   class="w-56 rounded-lg border-2 border-primary px-3 py-2.5 text-sm focus:outline-none">
            <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                Search
            </button>
            @if (request()->hasAny(['search', 'association_id', 'barangay_id', 'activity_status']))
                <a href="{{ route('mao.farmers.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
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
                    <th class="px-3 py-3">Farm Size</th>
                    <th class="px-3 py-3">Location</th>
                    <th class="px-3 py-3 text-center">Status</th>
                    <th class="px-3 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($farmers as $farmer)
                    <tr class="hover:bg-muted/60">
                        <td class="px-3 py-4 text-foreground">
                            FARM-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-3 py-4">
                            <p class="font-medium text-foreground">{{ $farmer->full_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $farmer->user?->username }}</p>
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $farmer->association?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-muted-foreground">
                            {{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '-' }}
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $farmer->barangay?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-center">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                {{ $farmer->activity_status === 'active' ? 'Active' : 'In-Active' }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-center">
                            <a href="{{ route('mao.farmers.show', $farmer) }}"
                               class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                View Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No farmers match these filters</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Approved farmer registrations appear here.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $farmers->links() }}</div>
</div>
@endsection
