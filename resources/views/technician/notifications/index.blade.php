@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('heading-fil', 'Mga Abiso')
@section('subheading', 'Announcements and alerts addressed to you.')

@section('content')

{{--
    Proposal section 66. These rows are written by the MAO alert system, one
    per recipient, so a technician only ever sees what was addressed to them.

    Nothing is ever deleted. What the office announced has to survive
    (section 81), so read and unread are the only states.
--}}

<div class="space-y-4">

    {{-- All / Unread. Reading a notification here never changes the
         "Assigned Reports" sidebar badge - that counts reports not yet
         inspected off the reports themselves. --}}
    <div class="flex gap-2">
        <x-ui.button size="sm" :variant="$filter === 'all' ? 'default' : 'outline'"
                     :href="route('technician.notifications.index')">
            All
        </x-ui.button>
        <x-ui.button size="sm" :variant="$filter === 'unread' ? 'default' : 'outline'"
                     :href="route('technician.notifications.index', ['filter' => 'unread'])">
            Unread{{ $unread > 0 ? " ({$unread})" : '' }}
        </x-ui.button>
    </div>

    @if ($unread > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border
                    bg-card px-4 py-3 shadow-sm">
            <p class="text-sm">
                <span class="font-semibold">{{ $unread }}</span>
                unread {{ \Illuminate\Support\Str::plural('notification', $unread) }}
            </p>

            <form method="POST" action="{{ route('technician.notifications.read-all') }}">
                @csrf
                @method('PUT')
                <x-ui.button size="sm" variant="outline" type="submit">Mark all as read</x-ui.button>
            </form>
        </div>
    @endif

    @forelse ($notifications as $notification)
        @php $alert = $notification->broadcast; @endphp

        <a href="{{ route('technician.notifications.show', $notification) }}"
           class="block rounded-xl border bg-card p-4 shadow-sm transition hover:border-primary/40 sm:p-5
                  {{ $notification->is_read ? 'border-border' : 'border-primary/40' }}">

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-base font-semibold">
                        @unless ($notification->is_read)
                            <span class="h-2 w-2 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
                        @endunless
                        <span class="truncate">{{ $alert?->title ?? 'Notification' }}</span>
                    </p>

                    <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {{ $alert?->message }}
                    </p>

                    <p class="mt-1.5 text-xs text-muted-foreground">
                        {{ $alert?->category_label ?? 'Announcement' }}
                        &middot; {{ $notification->created_at?->format('M d, Y g:i A') }}
                        @if ($alert?->createdBy)
                            &middot; {{ $alert->createdBy->display_name }}
                        @endif
                    </p>
                </div>

                @if ($alert?->priority && $alert->priority !== 'normal')
                    <x-ui.status :value="$alert->priority" />
                @endif
            </div>
        </a>
    @empty
        <x-ui.card :padded="false">
            <x-ui.empty :title="$filter === 'unread' ? 'All caught up' : 'No notifications yet'"
                        icon="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"
                        :message="$filter === 'unread' ? 'You have no unread notifications.' : 'Announcements from the Municipal Agriculture Office appear here.'" />
        </x-ui.card>
    @endforelse

    @if ($notifications->hasPages())
        <div class="pt-2">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
