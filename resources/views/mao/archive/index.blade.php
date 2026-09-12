@extends('layouts.app')

@section('title', 'Archive')
@section('heading', 'Archive')
@section('subheading', $view === 'deleted'
    ? 'Records permanently deleted from the system. Every field is kept for audit purposes, along with who deleted it and when. This list is read-only - deleted records cannot be restored.'
    : 'Records the office has taken out of circulation. Nothing here was deleted, and anything can be restored.')

@php
    $badge = 'inline-flex rounded-full px-2.5 py-1 text-xs font-medium';
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

    {{-- Tabs --}}
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

    {{-- Search --}}
    <form method="GET" class="mb-4 flex max-w-sm gap-2">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search this list"
               class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
        <button class="rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
            Search
        </button>
        @if (request('search'))
            <a href="{{ route('mao.archive.index', ['type' => $type, 'view' => $view]) }}"
               class="rounded-lg px-3 py-2 text-sm text-muted-foreground hover:text-foreground">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    @if ($view === 'deleted')
                        <tr>
                            <th class="px-5 py-3 font-medium">Record</th>
                            <th class="px-5 py-3 font-medium">Deleted By</th>
                            <th class="px-5 py-3 font-medium">Deleted On</th>
                        </tr>
                    @else
                        @switch($type)
                            @case('farmers')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Farmer</th>
                                    <th class="px-5 py-3 font-medium">Association</th>
                                    <th class="px-5 py-3 font-medium">Barangay</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('associations')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Association</th>
                                    <th class="px-5 py-3 text-center font-medium">Members</th>
                                    <th class="px-5 py-3 text-center font-medium">Allocations</th>
                                    <th class="px-5 py-3 font-medium">Archived</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('users')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Name</th>
                                    <th class="px-5 py-3 font-medium">Role</th>
                                    <th class="px-5 py-3 font-medium">Association</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('crops')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Crop</th>
                                    <th class="px-5 py-3 text-center font-medium">Records using it</th>
                                    <th class="px-5 py-3 font-medium">Archived</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('disasters')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Event</th>
                                    <th class="px-5 py-3 text-center font-medium">Damage reports</th>
                                    <th class="px-5 py-3 font-medium">Archived</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('assistance')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Assistance</th>
                                    <th class="px-5 py-3 font-medium">Type</th>
                                    <th class="px-5 py-3 text-center font-medium">Allocations</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break

                            @case('alerts')
                                <tr>
                                    <th class="px-5 py-3 font-medium">Alert</th>
                                    <th class="px-5 py-3 font-medium">Priority</th>
                                    <th class="px-5 py-3 text-center font-medium">Sent to</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                                @break
                        @endswitch
                    @endif
                </thead>

                <tbody class="divide-y divide-border">
                    @if ($view === 'deleted')
                        @forelse ($records as $record)
                            <tr class="hover:bg-muted/60">
                                <td class="px-5 py-3">
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
                                            @default
                                                {{ $record->name }}
                                        @endswitch
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        @switch($type)
                                            @case('farmers')
                                                {{ $record->user?->username }}
                                                @break
                                            @case('associations')
                                                {{ $record->barangay?->name ?? 'No barangay on file' }}
                                                @break
                                            @case('users')
                                                {{ $record->username }} &middot; {{ ucfirst($record->role) }}
                                                @break
                                            @case('assistance')
                                                {{ $record->type === 'cash' ? 'Cash' : 'In-Kind' }}
                                                @break
                                            @case('alerts')
                                                {{ $record->priority_label }}
                                                @break
                                            @default
                                                &nbsp;
                                        @endswitch
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ $record->deletedBy?->display_name ?? 'Unknown' }}
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ $record->deleted_at?->format('M d, Y g:i A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-14 text-center">
                                    <p class="text-sm font-medium text-muted-foreground">Nothing deleted here</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ $types[$type] }} that are permanently deleted will show up here, along with who deleted them and when.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    @else
                        @forelse ($records as $record)
                            <tr class="hover:bg-muted/60">
                                @switch($type)

                                    @case('farmers')
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->full_name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $record->user?->username }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->association?->name ?? '-' }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->barangay?->name ?? '-' }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.membership-applications.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this farmer's account? They will be able to log in again."
                                                      data-confirm-title="Restore farmer"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.membership-applications.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->full_name }}'s account will be removed from active lists. Their information is kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete farmer permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('associations')
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->name }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                Archived by {{ $record->archivedBy?->display_name ?? 'unknown' }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->farmers_count }}</td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->assistance_allocations_count }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.associations.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this association? It will appear again in farmer registration and allocation forms."
                                                      data-confirm-title="Restore association"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.associations.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->name }} will be removed from active lists. Its data is kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete association permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('users')
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->display_name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $record->username }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ ucfirst($record->role) }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">
                                            {{ $record->associationOfficer?->association?->name ?? '-' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.users.status', $record) }}"
                                                      data-confirm="Are you sure you want to restore this account? They will be able to log in again."
                                                      data-confirm-title="Restore account"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.users.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->display_name }}'s account will be removed from active lists. Its information is kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete account permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('crops')
                                        @php $used = $record->main_crops_count + $record->planting_record_crops_count + $record->damage_report_crops_count; @endphp
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->name }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                Archived by {{ $record->archivedBy?->display_name ?? 'unknown' }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $used }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.crops.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this crop? It will appear again as a choice on farmer and planting forms."
                                                      data-confirm-title="Restore crop"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.crops.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->name }} will be removed from active lists. Its data - and any records that cite it - are kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete crop permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('disasters')
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->name }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                Archived by {{ $record->archivedBy?->display_name ?? 'unknown' }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->damage_reports_count }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.disasters.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this disaster event? Farmers will be able to link new reports to it again."
                                                      data-confirm-title="Restore disaster event"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.disasters.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->name }} will be removed from active lists. Its data - and any reports that cite it - are kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete disaster event permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('assistance')
                                        <td class="px-5 py-3 font-medium text-foreground">{{ $record->name }}</td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->type === 'cash' ? 'Cash' : 'In-Kind' }}</td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->allocations_count }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.assistance.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this assistance item to the active catalogue?"
                                                      data-confirm-title="Restore assistance"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.assistance.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->name }} will be removed from the active catalogue. Its allocation and distribution history is kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete assistance permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break

                                    @case('alerts')
                                        <td class="px-5 py-3">
                                            <p class="font-medium text-foreground">{{ $record->title }}</p>
                                            <p class="max-w-sm truncate text-xs text-muted-foreground">{{ $record->message }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-muted-foreground">{{ $record->priority_label }}</td>
                                        <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->notifications_count }}</td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('mao.notifications.restore', $record) }}"
                                                      data-confirm="Are you sure you want to restore this alert?"
                                                      data-confirm-title="Restore alert"
                                                      data-confirm-action="Confirm Restore">
                                                    @csrf @method('PUT')
                                                    <button type="submit"
                                                            class="rounded-lg border border-input px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted/60">
                                                        Restore
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('mao.notifications.destroy', $record) }}"
                                                      data-confirm="Are you sure you want to continue? {{ $record->title }} will be removed from active lists. Its information - and the per-recipient notifications it already sent - are kept for audit purposes, and this deletion will be recorded."
                                                      data-confirm-title="Delete alert permanently"
                                                      data-confirm-detail="This action cannot be undone from this screen."
                                                      data-confirm-action="Delete Permanently"
                                                      data-confirm-tone="danger">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        @break
                                @endswitch
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
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
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
</div>
@endsection
