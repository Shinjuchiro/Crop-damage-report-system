@extends('layouts.app')

@section('title', 'Membership Applications')

@php
    $statusLabels = [
        'pending'  => ['Pending',  'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        'active'   => ['Approved', 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
        'inactive' => ['Archived', 'bg-secondary text-foreground'],
    ];
@endphp

@section('content')
<div x-data="{ archiving: null }" class="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6 space-y-5">

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <select name="status"
                class="min-w-52 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="all" @selected($status === 'all')>All Status</option>
            <option value="pending" @selected($status === 'pending')>Pending</option>
            <option value="active" @selected($status === 'active')>Approved</option>
            <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            <option value="inactive" @selected($status === 'inactive')>Archived</option>
        </select>

        <select name="barangay_id"
                class="min-w-52 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Barangay</option>
            @foreach ($barangays as $barangay)
                <option value="{{ $barangay->id }}" @selected(request('barangay_id') == $barangay->id)>
                    {{ $barangay->name }}
                </option>
            @endforeach
        </select>

        <div class="ml-auto flex items-center gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Applicant..."
                   class="w-56 rounded-lg border-2 border-primary px-3 py-2.5 text-sm focus:outline-none">
            <button class="rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
                Search
            </button>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                    <th class="px-3 py-3">Applicant ID</th>
                    <th class="px-3 py-3">Applicant Name</th>
                    <th class="px-3 py-3">Date Applied</th>
                    <th class="px-3 py-3">Farm Size</th>
                    <th class="px-3 py-3">Barangay</th>
                    <th class="px-3 py-3 text-center">Status</th>
                    <th class="px-3 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($applications as $application)
                    @php
                        $userStatus = $application->user?->status ?? 'pending';
                        [$label, $badge] = $statusLabels[$userStatus] ?? $statusLabels['pending'];
                    @endphp
                    <tr class="hover:bg-muted/60">
                        <td class="px-3 py-4 text-foreground">
                            APP-{{ str_pad($application->id, 3, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-3 py-4 font-medium text-foreground">{{ $application->full_name }}</td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $application->created_at?->format('M d, Y') }}</td>
                        <td class="px-3 py-4 text-muted-foreground">
                            {{ $application->farm_size_hectares ? $application->farm_size_hectares . ' ha' : '-' }}
                        </td>
                        <td class="px-3 py-4 text-muted-foreground">{{ $application->barangay?->name ?? '-' }}</td>
                        <td class="px-3 py-4 text-center">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $badge }}">{{ $label }}</span>
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('mao.membership-applications.show', $application) }}"
                                   class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                    View Details
                                </a>

                                @if ($userStatus !== 'inactive')
                                    <button type="button"
                                            @click="archiving = { id: {{ $application->id }}, name: @js($application->full_name) }"
                                            class="rounded bg-amber-100 px-4 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-200">
                                        Archive
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No applications match these filters</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                New farmer registrations appear here for review.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $applications->links() }}</div>

    {{-- Archive confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground">Archive this application?</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                <strong x-text="archiving?.name"></strong> will be taken out of the review queue.
                Nothing is deleted, and the record stays available under the Archived filter.
            </p>
            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/membership-applications') }}/${archiving?.id}/archive`" class="flex-1">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Confirm Archive
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
