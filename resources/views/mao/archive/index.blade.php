@extends('layouts.app')

@section('title', 'Archive')
@section('hideHeading', true)

@php
    $subheading = $view === 'deleted'
        ? 'Records permanently deleted from the system. Every field is kept for audit purposes, along with who deleted it and when. This list is read-only - deleted records cannot be restored.'
        : 'Records the office has taken out of circulation. Nothing here was deleted, and anything can be restored.';

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

    {{-- List + detail. Side by side from lg up; on a phone only one half
         shows at a time - the list, or (once a row's View link is
         followed) the detail panel full-screen with its own Back link. --}}
    <div class="lg:grid lg:grid-cols-[1fr_380px] lg:items-start lg:gap-5">

        {{-- LIST --}}
        <div class="{{ $selected ? 'hidden lg:block' : 'block' }}">
            <x-ui.card title="Archive" :description="$subheading">

                {{-- Archived / Deleted --}}
                <div class="mb-4 flex flex-wrap gap-2">
                    <a href="{{ route('mao.archive.index', ['type' => $type, 'view' => 'archived']) }}"
                       class="rounded-lg px-3.5 py-2 text-sm font-medium transition
                              {{ $view === 'archived'
                                    ? 'bg-foreground text-background'
                                    : 'border border-input text-muted-foreground hover:bg-muted/60 hover:text-foreground' }}">
                        Archived
                    </a>
                    <a href="{{ route('mao.archive.index', ['type' => $type, 'view' => 'deleted']) }}"
                       class="rounded-lg px-3.5 py-2 text-sm font-medium transition
                              {{ $view === 'deleted'
                                    ? 'bg-foreground text-background'
                                    : 'border border-input text-muted-foreground hover:bg-muted/60 hover:text-foreground' }}">
                        Deleted
                    </a>
                </div>

                {{-- Type tabs --}}
                <div class="mb-5 flex flex-wrap gap-2 border-b border-border pb-4">
                    @foreach ($types as $value => $label)
                        <a href="{{ route('mao.archive.index', ['type' => $value, 'view' => $view]) }}"
                           class="rounded-lg px-3.5 py-2 text-sm font-medium transition
                                  {{ $type === $value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/70 hover:text-foreground' }}">
                            {{ $label }}
                            <span class="ml-1 tabular-nums {{ $type === $value ? 'text-primary-foreground/80' : 'text-muted-foreground' }}">
                                ({{ $counts[$value] }})
                            </span>
                        </a>
                    @endforeach
                </div>

                {{-- Search. No visible button - Enter in the field submits
                     the form, matching every other filter bar. --}}
                <form method="GET" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <x-ui.input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Search this list" class="sm:max-w-sm" />
                    @if (request('search'))
                        <a href="{{ route('mao.archive.index', ['type' => $type, 'view' => $view]) }}"
                           class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b-2 border-border text-xs uppercase tracking-wide text-muted-foreground">
                            @if ($view === 'deleted')
                                <tr>
                                    <th class="px-4 py-3 font-medium">Record</th>
                                    <th class="px-4 py-3 font-medium">Deleted By</th>
                                    <th class="px-4 py-3 font-medium">Deleted On</th>
                                    <th class="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            @else
                                <tr>
                                    <th class="px-4 py-3 font-medium">Record</th>
                                    <th class="px-4 py-3 font-medium">Details</th>
                                    <th class="px-4 py-3 font-medium">Archived</th>
                                    <th class="px-4 py-3 text-right font-medium">Action</th>
                                </tr>
                            @endif
                        </thead>

                        <tbody class="divide-y divide-border">
                            @if ($view === 'deleted')
                                @forelse ($records as $record)
                                    <tr class="hover:bg-muted/60 {{ $selected?->id === $record->id ? 'bg-muted/60' : '' }}">
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-foreground">
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
                                        <td class="px-4 py-3 text-muted-foreground">{{ $record->deletedBy?->display_name ?? 'Unknown' }}</td>
                                        <td class="px-4 py-3 text-muted-foreground">{{ $record->deleted_at?->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <x-ui.button :href="$viewUrl($record->id)" variant="view" size="sm">View</x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-14 text-center">
                                            <p class="text-sm font-medium text-muted-foreground">Nothing deleted here</p>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                {{ $types[$type] }} that are permanently deleted will show up here, along with who deleted them and when.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            @else
                                @forelse ($records as $record)
                                    <tr class="hover:bg-muted/60 {{ $selected?->id === $record->id ? 'bg-muted/60' : '' }}">
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-foreground">
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
                                        <td class="px-4 py-3 text-muted-foreground">
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
                                        <td class="px-4 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <x-ui.button :href="$viewUrl($record->id)" variant="view" size="sm">View</x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-14 text-center">
                                            <p class="text-sm font-medium text-muted-foreground">Nothing archived here</p>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                {{ $types[$type] }} you archive will show up in this list, and can be restored at any time.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            @endif
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
                            {{ $types[$type] }} &middot; {{ $view === 'deleted' ? 'Deleted' : 'Archived' }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-semibold text-foreground">
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
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $record->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $record->barangay?->name ?? '-' }}</dd></div>
                            @break

                        @case('associations')
                            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $record->barangay?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Members</dt><dd class="font-medium text-foreground">{{ $record->farmers_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Officers</dt><dd class="font-medium text-foreground">{{ $record->officers_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Allocations</dt><dd class="font-medium text-foreground">{{ $record->assistance_allocations_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-medium text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('users')
                            <div><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $record->username }}</dd></div>
                            <div><dt class="text-muted-foreground">Role</dt><dd class="font-medium text-foreground">{{ ucfirst($record->role) }}</dd></div>
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $record->associationOfficer?->association?->name ?? '-' }}</dd></div>
                            @break

                        @case('crops')
                            <div><dt class="text-muted-foreground">Records using it</dt><dd class="font-medium text-foreground">{{ $record->main_crops_count + $record->planting_record_crops_count + $record->damage_report_crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-medium text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('disasters')
                            <div><dt class="text-muted-foreground">Damage reports</dt><dd class="font-medium text-foreground">{{ $record->damage_reports_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-medium text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
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
                            <div><dt class="text-muted-foreground">Created by</dt><dd class="font-medium text-foreground">{{ $record->createdBy?->display_name ?? 'Unknown' }}</dd></div>
                            @break

                        @case('crop_planting')
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $record->farmer?->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Crops planted</dt><dd class="font-medium text-foreground">{{ $record->crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Date submitted</dt><dd class="font-medium text-foreground">{{ $record->date_submitted?->format('M d, Y') }}</dd></div>
                            @if ($view !== 'deleted')
                                <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-medium text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @endif
                            @break

                        @case('damage_reports')
                            <div><dt class="text-muted-foreground">Farmer</dt><dd class="font-medium text-foreground">{{ $record->farmer?->full_name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $record->farmer?->association?->name ?? '-' }}</dd></div>
                            <div><dt class="text-muted-foreground">Crops on report</dt><dd class="font-medium text-foreground">{{ $record->crops_count }}</dd></div>
                            <div><dt class="text-muted-foreground">Status</dt><dd class="font-medium text-foreground">{{ \App\Models\DamageReport::STATUSES[$record->status] ?? ucfirst($record->status) }}</dd></div>
                            @if ($view !== 'deleted')
                                <div><dt class="text-muted-foreground">Archived by</dt><dd class="font-medium text-foreground">{{ $record->archivedBy?->display_name ?? 'Unknown' }}</dd></div>
                            @endif
                            @break
                    @endswitch

                    @if ($view === 'deleted')
                        <div><dt class="text-muted-foreground">Deleted by</dt><dd class="font-medium text-foreground">{{ $record->deletedBy?->display_name ?? 'Unknown' }}</dd></div>
                        <div><dt class="text-muted-foreground">Deleted on</dt><dd class="font-medium text-foreground">{{ $record->deleted_at?->format('M d, Y g:i A') }}</dd></div>
                    @else
                        <div><dt class="text-muted-foreground">Archived on</dt><dd class="font-medium text-foreground">{{ $record->archived_at?->format('M d, Y g:i A') }}</dd></div>
                    @endif
                </dl>

                @if ($view === 'deleted')
                    <p class="mt-5 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        Deleted records are read-only and cannot be restored from here.
                    </p>
                @else
                    @php
                        [$restoreRoute, $destroyRoute, $restoreConfirmText, $restoreTitle, $deleteConfirmText] = match ($type) {
                            'farmers' => [
                                route('mao.membership-applications.restore', $record),
                                route('mao.membership-applications.destroy', $record),
                                'Are you sure you want to restore this farmer\'s account? They will be able to log in again.',
                                'Restore farmer',
                                "Are you sure you want to continue? {$record->full_name}'s account will be removed from active lists. Their information is kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'associations' => [
                                route('mao.associations.restore', $record),
                                route('mao.associations.destroy', $record),
                                'Are you sure you want to restore this association? It will appear again in farmer registration and allocation forms.',
                                'Restore association',
                                "Are you sure you want to continue? {$record->name} will be removed from active lists. Its data is kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'users' => [
                                route('mao.users.status', $record),
                                route('mao.users.destroy', $record),
                                'Are you sure you want to restore this account? They will be able to log in again.',
                                'Restore account',
                                "Are you sure you want to continue? {$record->display_name}'s account will be removed from active lists. Its information is kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'crops' => [
                                route('mao.crops.restore', $record),
                                route('mao.crops.destroy', $record),
                                'Are you sure you want to restore this crop? It will appear again as a choice on farmer and planting forms.',
                                'Restore crop',
                                "Are you sure you want to continue? {$record->name} will be removed from active lists. Its data - and any records that cite it - are kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'disasters' => [
                                route('mao.disasters.restore', $record),
                                route('mao.disasters.destroy', $record),
                                'Are you sure you want to restore this disaster event? Farmers will be able to link new reports to it again.',
                                'Restore disaster event',
                                "Are you sure you want to continue? {$record->name} will be removed from active lists. Its data - and any reports that cite it - are kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'assistance' => [
                                route('mao.assistance.restore', $record),
                                route('mao.assistance.destroy', $record),
                                'Are you sure you want to restore this assistance item to the active catalogue?',
                                'Restore assistance',
                                "Are you sure you want to continue? {$record->name} will be removed from the active catalogue. Its allocation and distribution history is kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'alerts' => [
                                route('mao.notifications.restore', $record),
                                route('mao.notifications.destroy', $record),
                                'Are you sure you want to restore this alert?',
                                'Restore alert',
                                "Are you sure you want to continue? {$record->title} will be removed from active lists. Its information - and the per-recipient notifications it already sent - are kept for audit purposes, and this deletion will be recorded.",
                            ],
                            'crop_planting' => [
                                route('mao.crop-planting.restore', $record),
                                route('mao.crop-planting.destroy', $record),
                                'Are you sure you want to restore this planting record to the active monitoring list?',
                                'Restore planting record',
                                'Are you sure you want to continue? This planting record will be removed from active lists. Its data is kept for audit purposes, and this deletion will be recorded.',
                            ],
                            'damage_reports' => [
                                route('mao.damage-reports.restore', $record),
                                route('mao.damage-reports.destroy', $record),
                                "Are you sure you want to restore {$record->reference} to the active monitoring list?",
                                'Restore damage report',
                                "Are you sure you want to continue? {$record->reference} will be removed from active lists. Its data is kept for audit purposes, and this deletion will be recorded.",
                            ],
                        };
                    @endphp

                    <div class="mt-5 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row">
                        <form method="POST" action="{{ $restoreRoute }}" class="flex-1"
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
                        <form method="POST" action="{{ $destroyRoute }}" class="flex-1"
                              data-confirm="{{ $deleteConfirmText }}"
                              data-confirm-title="Delete permanently"
                              data-confirm-detail="This action cannot be undone from this screen."
                              data-confirm-action="Delete Permanently"
                              data-confirm-tone="danger">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                Delete
                            </button>
                        </form>
                    </div>
                @endif
            @endif
        </x-ui.detail-panel>
    </div>
</div>
@endsection
