@extends('layouts.app')

@section('title', 'Farmers Directory')
@section('hideHeading', true)

@php
    $accountBadges = [
        'active'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'inactive' => 'bg-secondary text-foreground',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $reportBadges = [
        'pending'            => 'bg-secondary text-foreground',
        'assigned'           => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'under_verification' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'verified'           => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'flagged'            => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
        'approved'           => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'rejected'           => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div>

    {{-- Summary --}}
    {{-- One figure per row on a phone, the way the map screen does it.
         Two across put five cards into three rows with a hole in the
         last one, and halved the width each label had to sit in. --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total Members" :value="number_format($summary['total'])"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9" />
        <x-ui.stat label="Active" :value="number_format($summary['active'])" hint="With recent activity"
                   icon="M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8" />
        <x-ui.stat label="Inactive" :value="number_format($summary['inactive'])"
                   icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM17 11h5" />
    </div>

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            <x-ui.card title="Farmers Directory"
                       :description="$association ? 'Members of ' . $association->name . ' and everything attached to them.' : 'Every approved farmer in the system and everything attached to them.'">
                <x-slot:actions>
                    <x-ui.button :href="route('mao.associations.index')" size="sm">
                        Manage Associations
                    </x-ui.button>
                </x-slot:actions>

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    {{-- Opened from one association: the page is already
                         that association's, so there is no dropdown to
                         choose from. The id travels in a hidden field so a
                         filter or a search keeps the scope. --}}
                    @if ($association)
                        <input type="hidden" name="association_id" value="{{ $association->id }}">
                    @else
                        <x-ui.select name="association_id" placeholder="All Associations" onchange="this.form.submit()"
                                     :options="$associations->pluck('name', 'id')" :selected="request('association_id')" class="sm:w-48" />
                    @endif

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-48" />

                    <x-ui.select name="activity_status" placeholder="Any Status" onchange="this.form.submit()"
                                 :options="['active' => 'Active', 'inactive' => 'Inactive']"
                                 :selected="request('activity_status')" class="sm:w-40" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search farmer"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny($association ? ['search', 'barangay_id', 'activity_status'] : ['search', 'association_id', 'barangay_id', 'activity_status']))
                        <a href="{{ route('mao.farmers.index', $association ? ['association_id' => $association->id] : []) }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                {{-- Table --}}
                {{-- ---------- PHONE: one card per farmer ----------
                     The same four facts the table carries, with the card
                     itself as the tap that opens the profile. --}}
                <ul class="space-y-3 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                    @forelse ($farmers as $farmer)
                        <li>
                            <x-ui.record-card :href="$viewUrl($farmer->id)"
                                              :eyebrow="'FARM-' . str_pad($farmer->id, 3, '0', STR_PAD_LEFT)"
                                              :title="$farmer->full_name"
                                              :status="$farmer->activity_status" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-border px-4 py-10 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No farmers match these filters</p>
                        </li>
                    @endforelse
                </ul>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-border text-sm font-bold text-foreground">
                                {{-- FOUR COLUMNS (revision section 3.1)

                                     Association, Location and a Records
                                     count used to sit between Name and
                                     Status. All three are on the profile
                                     behind View, and this is the list where
                                     trimming matters most: it is the one
                                     screen in the system that puts every
                                     registered farmer's association and
                                     barangay on display at once, which is
                                     more of the membership's details on one
                                     page than any single task needs
                                     (section 3.4).

                                     It is still one row per farmer, not one
                                     per record - that part of section 7 has
                                     not changed. --}}
                                <th class="px-3 py-3">Farmer ID</th>
                                <th class="px-3 py-3">Name</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            @forelse ($farmers as $farmer)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $farmer->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 py-4 text-foreground">
                                        FARM-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    {{-- The name alone. The username used to
                                         sit under it, which put every
                                         farmer's login on one screen for no
                                         task that needs it. --}}
                                    <td class="px-3 py-4">
                                        <p class="font-bold text-foreground">{{ $farmer->full_name }}</p>
                                    </td>

                                    <td class="px-3 py-4 text-center">
                                        <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $farmer->activity_status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' }}">
                                            {{ $farmer->activity_status === 'active' ? 'Active' : 'In-Active' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4 text-center">
                                        <x-ui.button :href="$viewUrl($farmer->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-14 text-center">
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a farmer from the list to view their full profile.">
            @if ($selected)
                <x-member.panel :farmer="$selected" :account="true">
                    @if (($selected->user?->status ?? 'pending') === 'pending')
                        <a href="{{ route('mao.membership-applications.show', $selected) }}"
                           class="mt-3 inline-block rounded-lg bg-green-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-900">
                            Review Registration
                        </a>
                    @endif
                </x-member.panel>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
