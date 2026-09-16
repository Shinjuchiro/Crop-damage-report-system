@extends('layouts.app')

@section('title', 'Membership Applications')
@section('hideHeading', true)

@php
    $statusLabels = [
        'pending'  => ['Pending',  'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
        'active'   => ['Approved', 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
        'inactive' => ['Archived', 'bg-secondary text-foreground'],
    ];

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div x-data="{ archiving: null, confirmAction: null }">
    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View is followed) the
         detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Membership Applications">

                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- Filters. No visible Search button - Enter in the field, or
                     choosing a dropdown option, submits the form. --}}
                <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.select name="status" placeholder="All Status" onchange="this.form.submit()"
                                 :options="['pending' => 'Pending', 'active' => 'Approved', 'rejected' => 'Rejected', 'inactive' => 'Archived']"
                                 :selected="$status === 'all' ? null : $status" class="sm:w-48" />

                    <x-ui.select name="barangay_id" placeholder="All Barangays" onchange="this.form.submit()"
                                 :options="$barangays->pluck('name', 'id')" :selected="request('barangay_id')" class="sm:w-48" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search applicant"
                                class="sm:min-w-[14rem] sm:flex-1" />

                    @if (request()->hasAny(['search', 'barangay_id']) || $status !== 'all')
                        <a href="{{ route('mao.membership-applications.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
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
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $application->id ? 'bg-muted/60' : '' }}">
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
                                        <div class="flex items-center justify-center gap-2">
                                            <x-ui.button :href="$viewUrl($application->id)" variant="view" size="sm">View</x-ui.button>

                                            @if ($userStatus !== 'inactive')
                                                <button type="button"
                                                        @click="archiving = { id: {{ $application->id }}, name: @js($application->full_name) }"
                                                        class="rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
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
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select an application from the list to review it.">
            @if ($selected)
                @php $farmer = $selected; $userStatus = $farmer->user?->status ?? 'pending'; [$label, $badge] = $statusLabels[$userStatus] ?? $statusLabels['pending']; @endphp

                <div class="mb-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        APP-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-0.5 text-lg font-semibold text-foreground">{{ $farmer->full_name }}</h3>
                    <span class="mt-2 inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $badge }}">{{ $label }}</span>
                </div>

                <div class="space-y-4 border-t border-border pt-4 text-sm">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Personal Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Date of Birth</dt><dd class="font-medium text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Sex</dt><dd class="font-medium capitalize text-foreground">{{ $farmer->sex }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Contact</dt><dd class="font-medium text-foreground">{{ $farmer->user->phone_number }}</dd></div>
                            <div><dt class="text-muted-foreground">Address</dt><dd class="font-medium text-foreground">{{ $farmer->address }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $farmer->barangay->name ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $farmer->association->name ?? '-' }}</dd></div>
                        </dl>
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Account Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $farmer->user->username }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Email</dt><dd class="max-w-[60%] truncate font-medium text-foreground">{{ $farmer->user->email }}</dd></div>
                        </dl>
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Ownership</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Farmer Type</dt><dd class="font-medium text-foreground">{{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</dd></div>
                            @if ($farmer->ownership_type === 'tenant')
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Land Owner</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_name ?? '-' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Owner Contact</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_contact ?? '-' }}</dd></div>
                            @else
                                <div>
                                    <dt class="text-muted-foreground">Barangay Certificate</dt>
                                    <dd class="font-medium text-foreground">
                                        @if ($farmer->barangay_certificate_path)
                                            <a href="{{ asset('storage/' . $farmer->barangay_certificate_path) }}" target="_blank" class="text-green-800 hover:underline">View</a>
                                        @else
                                            Not provided
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="border-t border-border pt-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Information</p>
                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Farm Size</dt><dd class="font-medium text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '-' }}</dd></div>
                            <div>
                                <dt class="text-muted-foreground">Main Crops</dt>
                                <dd class="font-medium text-foreground">
                                    @forelse ($farmer->mainCrops as $mc)
                                        {{ $mc->crop->name }}{{ $mc->crop_specify ? " ({$mc->crop_specify})" : '' }}@if(!$loop->last), @endif
                                    @empty - @endforelse
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                @if ($userStatus === 'pending')
                    <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row">
                        <button @click="confirmAction = 'approve'"
                                class="w-full rounded-lg bg-green-800 px-3 py-2 text-sm font-semibold text-white hover:bg-green-900">
                            Approve
                        </button>
                        <button @click="confirmAction = 'reject'"
                                class="w-full rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Reject
                        </button>
                    </div>

                    {{-- Approve confirmation --}}
                    <div x-show="confirmAction === 'approve'" x-cloak
                         class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
                        <div class="w-full max-w-md rounded-xl bg-card p-6">
                            <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Approval</h3>
                            <p class="mb-5 text-sm text-muted-foreground">
                                Are you sure you want to approve <strong>{{ $farmer->full_name }}</strong>?
                                The farmer will gain full access to the system.
                            </p>
                            <div class="flex gap-3">
                                <button @click="confirmAction = null"
                                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                    Cancel
                                </button>
                                <form method="POST" action="{{ route('mao.membership-applications.approve', $farmer) }}" class="flex-1">
                                    @csrf @method('PUT')
                                    <button class="w-full rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                                        Confirm Approval
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Reject confirmation --}}
                    <div x-show="confirmAction === 'reject'" x-cloak
                         class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
                        <div class="w-full max-w-md rounded-xl bg-card p-6">
                            <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Rejection</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Are you sure you want to reject this registration?
                            </p>
                            <form method="POST" action="{{ route('mao.membership-applications.reject', $farmer) }}">
                                @csrf @method('PUT')
                                <label class="mb-1 block text-sm font-medium text-foreground">Reason (optional)</label>
                                <textarea name="rejection_reason" rows="3"
                                          class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600"></textarea>
                                <div class="flex gap-3">
                                    <button type="button" @click="confirmAction = null"
                                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="flex-1 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                                        Confirm Rejection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        This application has already been reviewed.
                    </p>
                @endif
            @endif
        </x-ui.detail-panel>
    </div>

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
