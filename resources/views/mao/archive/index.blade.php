@extends('layouts.app')

@section('title', 'Archive')
@section('hideHeading', true)

@php
    $subheading = 'Records the office has taken out of circulation. '
        . 'Nothing here is deleted, and anything can be restored.';

    $viewUrl = fn ($id) => request()->fullUrlWithQuery(['selected' => $id]);
    $backUrl = request()->fullUrlWithoutQuery(['selected']);
@endphp

@section('content')
<div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/60 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

        {{-- LIST --}}
        <div>
            <x-ui.card title="Archive" :description="$subheading">

                {{-- RECORD TYPE: A FILTER, NOT A ROW OF TABS

                     There are nine kinds of archived record, so as tabs they
                     ran the full width of the screen and wrapped onto a
                     second line on anything narrower, which made the strip
                     the loudest thing on a page whose job is a quiet list.
                     Every other list in the system picks its scope from a
                     dropdown on the filter row, and this one now does too.

                     The count stays, inside each option, because it is the
                     reason to choose one: it says where the archived records
                     actually are before you go looking. --}}
                <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-ui.select name="type" onchange="this.form.submit()" class="sm:w-64"
                                 :options="collect($types)->mapWithKeys(
                                     fn ($label, $value) => [$value => $label . ' (' . $counts[$value] . ')']
                                 )"
                                 :selected="$type" />

                    <x-ui.input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Search this list" class="sm:max-w-sm" />

                    @if (request('search'))
                        <a href="{{ route('mao.archive.index', ['type' => $type]) }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b-2 border-border text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-3 sm:px-4 py-3 font-medium">Record</th>
                                <th class="px-3 sm:px-4 py-3 font-medium">Details</th>
                                <th class="px-3 sm:px-4 py-3 font-medium">Archived</th>
                                <th class="px-3 sm:px-4 py-3 text-right font-medium">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            @forelse ($records as $record)
                                <tr class="hover:bg-muted/60 {{ $selected?->id === $record->id ? 'bg-muted/60' : '' }}">
                                    <td class="px-3 sm:px-4 py-3">
                                        <p class="{{ in_array($type, ['farmers', 'users', 'crop_planting', 'associations']) ? 'font-bold' : 'font-medium' }} text-foreground">
                                            @switch($type)
                                                @case('farmers')
                                                    {{ $record->full_name }}
                                                    @break
                                                @case('users')
                                                    {{ $record->display_name }}
                                                    @break
                                                @case('alerts')
                                                    {{ $record->title }}
                                                    @break
                                                @case('crop_planting')
                                                    {{ $record->farmer?->full_name ?? 'Unknown farmer' }}
                                                    @break
                                                @case('damage_reports')
                                                    {{ $record->reference }}
                                                    @break
                                                @default
                                                    {{ $record->name }}
                                            @endswitch
                                        </p>
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 text-muted-foreground">
                                        @switch($type)
                                            @case('farmers')
                                                {{ $record->association?->name ?? '-' }}
                                                @break
                                            @case('associations')
                                                {{ $record->farmers_count }} members
                                                @break
                                            @case('users')
                                                {{ ucfirst($record->role) }}
                                                @break
                                            @case('crops')
                                                {{ $record->main_crops_count + $record->planting_record_crops_count + $record->damage_report_crops_count }} records using it
                                                @break
                                            @case('disasters')
                                                {{ $record->damage_reports_count }} damage reports
                                                @break
                                            @case('assistance')
                                                {{ $record->type === 'cash' ? 'Cash' : 'In-Kind' }}
                                                @break
                                            @case('alerts')
                                                {{ $record->priority_label }}
                                                @break
                                            @case('crop_planting')
                                                {{ $record->crops_count }} crop(s)
                                                @break
                                            @case('damage_reports')
                                                {{ $record->farmer?->full_name ?? '-' }}
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                    <td class="px-3 sm:px-4 py-3 text-right">
                                        <x-ui.button :href="$viewUrl($record->id)" variant="view" size="sm">View</x-ui.button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 sm:px-4 py-14 text-center">
                                        <p class="text-sm font-medium text-muted-foreground">Nothing archived here</p>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            {{ $types[$type] }} you archive will show up in this list, and can be restored at any time.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $records->links() }}</div>
            </x-ui.card>
        </div>

        {{-- DETAIL --}}
        <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-5 lg:mt-0"
                            empty-text="Select a record from the list to view its full details.">
            @if ($selected)
                @php $record = $selected; @endphp

                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            {{ $types[$type] }} &middot; Archived
                        </p>
                        <h3 class="mt-0.5 text-lg {{ in_array($type, ['farmers', 'users', 'crop_planting', 'associations']) ? 'font-bold' : 'font-semibold' }} text-foreground">
                            @switch($type)
                                @case('farmers') {{ $record->full_name }} @break
                                @case('users') {{ $record->display_name }} @break
                                @case('alerts') {{ $record->title }} @break
                                @case('crop_planting') {{ $record->farmer?->full_name ?? 'Unknown farmer' }} @break
                                @case('damage_reports') {{ $record->reference }} @break
                                @default {{ $record->name }}
                            @endswitch
                        </h3>
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    @switch($type)
                        @case('farmers')
                            <div><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $record->user?->username ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $record->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $record->barangay?->name ?? '-' }}</dd></div>
                            @break

                        @case('associations')
                            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $record->barangay?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Members</dt><dd class="font-medium text-foreground">{{ $record->farmers_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Officers</dt><dd class="font-medium text-foreground">{{ $record->officers_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Allocations</dt><dd class="font-medium text-foreground">{{ $record->assistance_allocations_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-bold text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('users')
                            <div><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $record->username }}</dd></div>
                            <div><dt class="text-muted-foreground">Role</dt><dd class="font-medium text-foreground">{{ ucfirst($record->role) }}</dd></div>
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $record->associationOfficer?->association?->name ?? '-' }}</dd></div>
                            @break

                        @case('crops')
                            <div><dt class="text-muted-foreground">Records using it</dt><dd class="font-medium text-foreground">{{ $record->main_crops_count + $record->planting_record_crops_count + $record->damage_report_crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-bold text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('disasters')
                            <div><dt class="text-muted-foreground">Damage reports</dt><dd class="font-medium text-foreground">{{ $record->damage_reports_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-bold text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('assistance')
                            <div><dt class="text-muted-foreground">Type</dt><dd class="font-medium text-foreground">{{ $record->type === 'cash' ? 'Cash' : 'In-Kind' }}</dd></div>
                            <div><dt class="text-muted-foreground">Disaster</dt><dd class="font-medium text-foreground">{{ $record->disaster?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Allocations</dt><dd class="font-medium text-foreground">{{ $record->allocations_count }}</dd></div>
                            @break

                        @case('alerts')
                            <div><dt class="text-muted-foreground">Message</dt><dd class="font-medium text-foreground">{{ $record->message }}</dd></div>
                            <div><dt class="text-muted-foreground">Priority</dt><dd class="font-medium text-foreground">{{ $record->priority_label }}</dd></div>
                            <div><dt class="text-muted-foreground">Sent to</dt><dd class="font-medium text-foreground">{{ $record->notifications_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Created by</dt><dd class="font-bold text-foreground">{{ $record->createdBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('crop_planting')
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $record->farmer?->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Crops planted</dt><dd class="font-medium text-foreground">{{ $record->crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Date submitted</dt><dd class="font-medium text-foreground">{{ $record->date_submitted?->format('M d, Y') }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-bold text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('damage_reports')
                            <div><dt class="text-muted-foreground">Farmer</dt><dd class="font-bold text-foreground">{{ $record->farmer?->full_name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $record->farmer?->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Crops on report</dt><dd class="font-medium text-foreground">{{ $record->crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Status</dt><dd class="font-medium text-foreground">{{ \App\Models\DamageReport::STATUSES[$record->status] ?? ucfirst($record->status) }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-bold text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break
                    @endswitch

                    <div><dt class="text-muted-foreground">Archived on</dt><dd class="font-medium text-foreground">{{ $record->archived_at?->format('M d, Y g:i A') }}</dd></div>
                </dl>

                {{-- Restore is the only action here. Permanent delete used to
                     sit beside it; the office archives and nothing in the
                     system removes a record, so there is one button and
                     nothing destructive to reach by mistake. --}}
                @php
                    [$restoreRoute, $restoreConfirmText, $restoreTitle] = match ($type) {
                            'farmers' => [
                                route('mao.membership-applications.restore', $record),
                                'Are you sure you want to restore this farmer\'s account? They will be able to log in again.',
                                'Restore farmer',
                            ],
                            'associations' => [
                                route('mao.associations.restore', $record),
                                'Are you sure you want to restore this association? It will appear again in farmer registration and allocation forms.',
                                'Restore association',
                            ],
                            'users' => [
                                route('mao.users.status', $record),
                                'Are you sure you want to restore this account? They will be able to log in again.',
                                'Restore account',
                            ],
                            'crops' => [
                                route('mao.crops.restore', $record),
                                'Are you sure you want to restore this crop? It will appear again as a choice on farmer and planting forms.',
                                'Restore crop',
                            ],
                            'disasters' => [
                                route('mao.disasters.restore', $record),
                                'Are you sure you want to restore this disaster event? Farmers will be able to link new reports to it again.',
                                'Restore disaster event',
                            ],
                            'assistance' => [
                                route('mao.assistance.restore', $record),
                                'Are you sure you want to restore this assistance item to the active catalogue?',
                                'Restore assistance',
                            ],
                            'alerts' => [
                                route('mao.notifications.restore', $record),
                                'Are you sure you want to restore this alert?',
                                'Restore alert',
                            ],
                            'crop_planting' => [
                                route('mao.crop-planting.restore', $record),
                                'Are you sure you want to restore this planting record to the active monitoring list?',
                                'Restore planting record',
                            ],
                            'damage_reports' => [
                                route('mao.damage-reports.restore', $record),
                                "Are you sure you want to restore {$record->reference} to the active monitoring list?",
                                'Restore damage report',
                            ],
                    };
                @endphp

                <div class="mt-5 border-t border-border pt-4">
                    <form method="POST" action="{{ $restoreRoute }}"
                          data-confirm="{{ $restoreConfirmText }}"
                          data-confirm-title="{{ $restoreTitle }}"
                          data-confirm-action="Confirm Restore">
                        @csrf @method('PUT')
                        @if ($type === 'users')
                            <input type="hidden" name="status" value="active">
                        @endif
                        <button type="submit" class="w-full rounded-lg border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Restore
                        </button>
                    </form>
                </div>
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
