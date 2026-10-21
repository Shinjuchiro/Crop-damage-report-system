{{--
    THE FULL NOTIFICATIONS PAGE, FOR EVERY ROLE

        <x-notification.list :items="$notifications" :filter="$filter"
                             :unread="$unread" index-route="farmer.notifications.index"
                             show-route="farmer.notifications.show"
                             :read-all="route('farmer.notifications.read-all')"
                             empty-message="Advisories from the office will appear here." />

    Four roles had four of these, written at different times, and they had
    drifted: one used cards with a coloured border, one used a flat list
    with a dot, they printed the time differently, and only two had a "mark
    all read". They are now one component, so the bell's dropdown and all
    four pages show a notification the same way.

    NEW, THEN EARLIER

    The same split the apps people already use make: what you have not read
    yet, then everything else. It is more useful than a plain reverse
    chronological list, because the question is almost never "what arrived
    on Tuesday" - it is "what have I not seen".

    The headings only appear on the All tab. On Unread everything is new by
    definition, and a heading over a list of one kind of thing is furniture.

    props
      items        a paginator of Notification rows, broadcast loaded
      filter       'all' or 'unread', from the controller
      unread       total unread for this user
      indexRoute   route NAME of this page, for the two tabs
      showRoute    route NAME for opening one notification
      readAll      url of the mark-all-read action, or null
      emptyMessage what to say when there is nothing at all
--}}

@props([
    'items',
    'filter'       => 'all',
    'unread'       => 0,
    'indexRoute',
    'showRoute',
    'readAll'      => null,
    'emptyMessage' => 'Announcements and updates will appear here.',
])

@php
    $isUnreadTab = $filter === 'unread';

    // Split only on the All tab; see the note above.
    $fresh  = $isUnreadTab ? collect() : $items->getCollection()->filter(fn ($row) => ! $row->is_read);
    $older  = $isUnreadTab ? $items->getCollection() : $items->getCollection()->filter(fn ($row) => $row->is_read);
@endphp

<div class="space-y-4">

    {{-- All / Unread. Links, not script: the list is paginated, so each tab
         is a different query and deserves its own URL. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route($indexRoute) }}"
               class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors
                      {{ ! $isUnreadTab ? 'bg-primary text-primary-foreground' : 'border border-input text-muted-foreground hover:bg-muted/60' }}">
                All
            </a>
            <a href="{{ route($indexRoute, ['filter' => 'unread']) }}"
               class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors
                      {{ $isUnreadTab ? 'bg-primary text-primary-foreground' : 'border border-input text-muted-foreground hover:bg-muted/60' }}">
                Unread{{ $unread > 0 ? ' (' . $unread . ')' : '' }}
            </a>
        </div>

        @if ($readAll && $unread > 0)
            <form method="POST" action="{{ $readAll }}"
                  data-confirm="All {{ $unread }} unread notifications will be marked as read."
                  data-confirm-title="Mark everything as read?"
                  data-confirm-action="Yes, mark all read">
                @csrf @method('PUT')
                <x-ui.button type="submit" variant="outline" size="sm">Mark all as read</x-ui.button>
            </form>
        @endif
    </div>

    @if ($items->isEmpty())
        <x-ui.card :padded="false">
            <x-ui.empty :title="$isUnreadTab ? 'All caught up' : 'No notifications yet'"
                        icon="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"
                        :message="$isUnreadTab ? 'You have no unread notifications.' : $emptyMessage" />
        </x-ui.card>
    @else
        @if ($fresh->isNotEmpty())
            <div>
                <h2 class="mb-2 px-1 text-base font-bold text-foreground">New</h2>
                <x-ui.card :padded="false">
                    @foreach ($fresh as $row)
                        <div class="border-b border-border last:border-0">
                            <x-notification.row :notification="$row" :href="route($showRoute, $row)" />
                        </div>
                    @endforeach
                </x-ui.card>
            </div>
        @endif

        @if ($older->isNotEmpty())
            <div>
                @unless ($isUnreadTab)
                    <h2 class="mb-2 px-1 text-base font-bold text-foreground">Earlier</h2>
                @endunless

                <x-ui.card :padded="false">
                    @foreach ($older as $row)
                        <div class="border-b border-border last:border-0">
                            <x-notification.row :notification="$row" :href="route($showRoute, $row)" />
                        </div>
                    @endforeach
                </x-ui.card>
            </div>
        @endif

        @if ($items->hasPages())
            <div class="pt-1">{{ $items->links() }}</div>
        @endif
    @endif
</div>
