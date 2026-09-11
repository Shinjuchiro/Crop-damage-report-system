@extends('layouts.app')

@section('title', 'Members')
@section('heading', 'Members')
@section('heading-fil', 'Mga Kasapi')
@section('subheading', 'The farmers registered under ' . $association->name . '.')

@section('content')

{{--
    Proposal section 61. Read only: the association monitors its members, it
    does not edit their records. A farmer changes their own details, and the
    MAO approves registrations.

    Below sm the table becomes cards, because an officer checking a member on
    a phone should not have to scroll sideways.
--}}

<div class="space-y-4">

    <x-ui.card>
        <form method="GET" action="{{ route('association.members.index') }}"
              class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">

            <div class="space-y-1.5 lg:col-span-2">
                <label class="block text-sm font-medium" for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="First or last name"
                       class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block text-sm font-medium" for="barangay">Barangay</label>
                <select id="barangay" name="barangay"
                        class="h-10 w-full rounded-md border border-input bg-card px-3 text-sm shadow-sm">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $option)
                        <option value="{{ $option->id }}"
                                @selected((string) ($filters['barangay'] ?? '') === (string) $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit" class="flex-1 sm:flex-none">Apply</x-ui.button>
                <x-ui.button variant="outline" :href="route('association.members.index')">Clear</x-ui.button>
            </div>
        </form>

        {{-- A one-tap shortcut for the question officers actually ask most --}}
        <div class="mt-3 flex flex-wrap gap-2 border-t border-border pt-3">
            <x-ui.button size="sm" :variant="($filters['affected'] ?? '') === 'yes' ? 'default' : 'outline'"
                         :href="route('association.members.index', ['affected' => 'yes'])">
                Only members with a damage report
            </x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card :padded="false">
        @if ($members->isEmpty())
            <x-ui.empty title="No members match this"
                        icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9"
                        message="Either nobody has registered under your association yet, or the filters above are hiding everyone. Farmers appear here once the Municipal Agriculture Office approves their registration.">
                <x-ui.button variant="outline" :href="route('association.members.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach ($members as $member)
                    <li>
                        <a href="{{ route('association.members.show', $member) }}"
                           class="block px-4 py-4 transition active:bg-muted">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $member->full_name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $member->barangay?->name ?? 'Barangay not set' }}
                                    </p>
                                </div>
                                <x-ui.status :value="$member->activity_status" />
                            </div>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $member->planting_records_count }} planting
                                &middot; {{ $member->damage_reports_count }} damage
                                {{ $member->damage_reports_count === 1 ? 'report' : 'reports' }}
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden sm:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Member</th>
                            <th>Barangay</th>
                            <th>Farm Size</th>
                            <th>Planting</th>
                            <th>Damage Reports</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($members as $member)
                        <tr>
                            <td>
                                <span class="font-medium">{{ $member->full_name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ $member->user?->phone_number ?: 'No contact number' }}
                                </span>
                            </td>

                            <td class="text-muted-foreground">{{ $member->barangay?->name ?? '-' }}</td>

                            <td class="whitespace-nowrap">
                                {{ $member->farm_size_hectares
                                    ? number_format($member->farm_size_hectares, 2) . ' ha'
                                    : '-' }}
                            </td>

                            <td class="text-muted-foreground">{{ $member->planting_records_count }}</td>

                            <td>
                                @if ($member->damage_reports_count > 0)
                                    <x-ui.badge variant="warning">{{ $member->damage_reports_count }}</x-ui.badge>
                                @else
                                    <span class="text-muted-foreground">0</span>
                                @endif
                            </td>

                            <td>
                                <x-ui.status :value="$member->activity_status" />
                                @if ($member->activity_status === 'inactive')
                                    <span class="mt-1 block text-xs text-muted-foreground">
                                        {{ $member->months_inactive }} months
                                    </span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="outline"
                                             :href="route('association.members.show', $member)">
                                    View
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif

        @if ($members->hasPages())
            <x-slot:footer>{{ $members->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
