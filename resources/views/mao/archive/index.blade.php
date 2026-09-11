@extends('layouts.app')

@section('title', 'Archive')
@section('heading', 'Archive')
@section('subheading', "Records the office has taken out of circulation. Nothing here was deleted, and anything can be restored.")

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

    {{-- Tabs --}}
    <div class="mb-5 flex flex-wrap gap-2 border-b border-border pb-4">
        @foreach ($types as $value => $label)
            <a href="{{ route('mao.archive.index', ['type' => $value]) }}"
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
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search this list"
               class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
        <button class="rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
            Search
        </button>
        @if (request('search'))
            <a href="{{ route('mao.archive.index', ['type' => $type]) }}"
               class="rounded-lg px-3 py-2 text-sm text-muted-foreground hover:text-foreground">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
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
                </thead>

                <tbody class="divide-y divide-border">
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
                                    <td class="px-5 py-3 text-right">
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
                                    <td class="px-5 py-3 text-right">
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
                                    <td class="px-5 py-3 text-right">
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
                                    </td>
                                    @break

                                @case('crops')
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-foreground">{{ $record->name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Archived by {{ $record->archivedBy?->display_name ?? 'unknown' }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">
                                        {{ $record->main_crops_count + $record->planting_record_crops_count + $record->damage_report_crops_count }}
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">{{ $record->archived_at?->format('M d, Y') }}</td>
                                    <td class="px-5 py-3 text-right">
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
                                    <td class="px-5 py-3 text-right">
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
                                    </td>
                                    @break

                                @case('assistance')
                                    <td class="px-5 py-3 font-medium text-foreground">{{ $record->name }}</td>
                                    <td class="px-5 py-3 text-muted-foreground">{{ $record->type === 'cash' ? 'Cash' : 'In-Kind' }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->allocations_count }}</td>
                                    <td class="px-5 py-3 text-right">
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
                                    </td>
                                    @break

                                @case('alerts')
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-foreground">{{ $record->title }}</p>
                                        <p class="max-w-sm truncate text-xs text-muted-foreground">{{ $record->message }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">{{ $record->priority_label }}</td>
                                    <td class="px-5 py-3 text-center tabular-nums text-muted-foreground">{{ $record->notifications_count }}</td>
                                    <td class="px-5 py-3 text-right">
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
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
</div>
@endsection
