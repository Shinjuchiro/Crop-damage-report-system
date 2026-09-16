@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subheading', 'Advisories and announcements from the Municipal Agriculture Office. Mga abiso mula sa MAO.')

@section('header-actions')
    @if ($unread > 0)
        <form method="POST" action="{{ route('farmer.notifications.read-all') }}"
              data-confirm="All {{ $unread }} unread notifications will be marked as read."
              data-confirm-title="Mark everything as read?"
              data-confirm-action="Yes, mark all read">
            @csrf @method('PUT')
            <x-ui.button type="submit" variant="outline">Mark all as read</x-ui.button>
        </form>
    @endif
@endsection

@section('content')
<div class="space-y-4">
    {{-- All / Unread. Reading a notification here never changes a sidebar
         badge - those count pending work on the actual records instead. --}}
    <div class="flex gap-2">
        <x-ui.button size="sm" :variant="$filter === 'all' ? 'default' : 'outline'"
                     :href="route('farmer.notifications.index')">
            All
        </x-ui.button>
        <x-ui.button size="sm" :variant="$filter === 'unread' ? 'default' : 'outline'"
                     :href="route('farmer.notifications.index', ['filter' => 'unread'])">
            Unread{{ $unread > 0 ? " ({$unread})" : '' }}
        </x-ui.button>
    </div>

    <x-ui.card :padded="false">
        @forelse ($notifications as $notification)
            @php $alert = $notification->broadcast; @endphp

            <a href="{{ route('farmer.notifications.show', $notification) }}"
               class="flex gap-4 border-b border-border px-5 py-4 transition-colors last:border-0 hover:bg-muted/60">

                {{-- Unread marker. A dot rather than a heavy background, so a
                     long list of read messages stays calm. --}}
                <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full
                             {{ $notification->is_read ? 'bg-transparent' : 'bg-primary' }}"
                      @if (! $notification->is_read) aria-label="Unread" @endif></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm {{ $notification->is_read ? 'font-medium' : 'font-semibold' }} text-card-foreground">
                            {{ $alert?->title ?? 'Notification' }}
                        </p>
                        @if ($alert)
                            <x-ui.status :value="$alert->priority" />
                        @endif
                    </div>

                    <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {{ \Illuminate\Support\Str::limit($alert?->message, 160) }}
                    </p>

                    <p class="mt-1.5 text-xs text-muted-foreground">
                        {{ $alert?->category_label }}
                        &middot; {{ \Illuminate\Support\Carbon::parse($notification->created_at)->diffForHumans() }}
                    </p>
                </div>
            </a>
        @empty
            <x-ui.empty :title="$filter === 'unread' ? 'All caught up' : 'No notifications yet'"
                        icon="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"
                        :message="$filter === 'unread' ? 'You have no unread notifications.' : 'Advisories from the Municipal Agriculture Office will appear here.'" />
        @endforelse

        @if ($notifications->hasPages())
            <x-slot:footer>{{ $notifications->links() }}</x-slot:footer>
        @endif
    </x-ui.card>
</div>
@endsection
