{{--
    THE BELL'S DROPDOWN

        <x-notification.panel :items="$bellItems" :unread="$bellCount"
                              :see-all="$bellRoute" :read-all="$bellReadAll"
                              :show-route="$bellShowRoute" />

    The panel that drops out of the bell in the top bar, built to the shape
    of the one on everyone's phone: a heading, All and Unread, the latest
    rows, and a link to the whole list at the bottom.

    WHY A PANEL AT ALL, WHEN THERE IS ALREADY A PAGE

    Because the question the bell answers is almost always "anything new?",
    and the answer is almost always "no". Making that a page load, and then
    a trip back to whatever you were doing, is the long way round for a
    question asked twenty times a day. The full page stays, and See all at
    the bottom is the door to it, for when the answer is yes.

    Nothing here is fetched: the rows are rendered with the page, the same
    way the unread count always has been. So the panel opens instantly and
    there is no spinner, and it is as fresh as the page you are standing on.

    props
      items      the latest few Notification rows, broadcast loaded
      unread     how many are unread, in total, not just in this list
      seeAll     url of the role's full notifications page
      readAll    url of the role's "mark all read" action, or null
      showRoute  route NAME for opening one notification
--}}

@props([
    'items'     => null,
    'unread'    => 0,
    'seeAll'    => null,
    'readAll'   => null,
    'showRoute' => null,
])

@php
    $items  = $items ?? collect();
    $groups = \App\Support\NotificationGroups::group($items);
@endphp

<div x-data="{ open: false, tab: 'all' }" class="relative">

    <button type="button"
            @click="open = ! open"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Notifications{{ $unread > 0 ? ', ' . $unread . ' unread' : '' }}"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground
                   transition-colors hover:bg-accent hover:text-accent-foreground"
            :class="open ? 'bg-accent text-accent-foreground' : ''">

        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"/>
        </svg>

        {{-- The count sits on the corner of the button, not on the bell
             itself. Inset over the drawing, a single digit read as part of
             the icon and a "9+" squashed into it. --}}
        @if ($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center
                         rounded-full bg-destructive px-1 text-[10px] font-bold leading-none
                         text-destructive-foreground ring-2 ring-card"
                  aria-hidden="true">
                {{ $unread > 9 ? '9+' : $unread }}
            </span>
        @endif
    </button>

    {{-- The dim, phones only. A panel this size covers most of a phone
         screen, and without something behind it the page underneath keeps
         inviting taps that only close it. --}}
    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 z-30 bg-foreground/20 sm:hidden"
         @click="open = false" aria-hidden="true"></div>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         @click.outside="open = false"
         @keydown.escape.window="open = false"
         role="menu"
         class="absolute right-0 top-full z-40 mt-2 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden
                rounded-2xl border border-border bg-card shadow-xl">

        {{-- Heading --}}
        <div class="flex items-center justify-between gap-3 px-4 pb-2 pt-3.5">
            <h2 class="text-xl font-bold tracking-tight text-card-foreground">Notifications</h2>

            @if ($readAll && $unread > 0)
                <form method="POST" action="{{ $readAll }}"
                      data-confirm="All {{ $unread }} unread notifications will be marked as read."
                      data-confirm-title="Mark everything as read?"
                      data-confirm-action="Yes, mark all read">
                    @csrf @method('PUT')
                    <button type="submit"
                            class="rounded-lg px-2 py-1 text-xs font-semibold text-primary transition-colors hover:bg-accent">
                        Mark all read
                    </button>
                </form>
            @endif
        </div>

        {{-- All / Unread. Client side, because every row is already here:
             a page load to hide the read ones would be slower than the
             question is worth. --}}
        <div class="flex gap-2 px-4 pb-2">
            <button type="button" @click="tab = 'all'"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition-colors"
                    :class="tab === 'all'
                        ? 'bg-accent text-primary'
                        : 'text-muted-foreground hover:bg-muted/60'">
                All
            </button>
            <button type="button" @click="tab = 'unread'"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition-colors"
                    :class="tab === 'unread'
                        ? 'bg-accent text-primary'
                        : 'text-muted-foreground hover:bg-muted/60'">
                Unread{{ $unread > 0 ? ' (' . $unread . ')' : '' }}
            </button>
        </div>

        <div class="max-h-[60vh] overflow-y-auto border-t border-border">
            @forelse ($groups as $label => $rows)
                {{-- A group whose rows are all read has nothing to show on the
                     Unread tab, so its heading goes with them. --}}
                <div @if ($rows->every(fn ($r) => $r->is_read)) x-show="tab === 'all'" @endif>
                    <h3 class="bg-muted/40 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-muted-foreground">
                        {{ $label }}
                    </h3>

                    @foreach ($rows as $item)
                        {{-- A read row is simply not rendered on the Unread tab.
                             x-show rather than a filter, so switching tabs costs
                             nothing and the scroll position survives it. --}}
                        <div @if ($item->is_read) x-show="tab === 'all'" @endif
                             class="border-b border-border last:border-0">
                            <x-notification.row
                                :notification="$item"
                                :href="$showRoute ? route($showRoute, $item) : null"
                                compact
                                />
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="px-4 py-10 text-center text-sm text-muted-foreground">
                    Nothing yet. Advisories and updates will appear here.
                </p>
            @endforelse

            {{-- Only reachable when everything in the list has been read. --}}
            @if ($items->isNotEmpty() && $unread === 0)
                <p x-show="tab === 'unread'" x-cloak
                   class="px-4 py-10 text-center text-sm text-muted-foreground">
                    You are all caught up.
                </p>
            @endif
        </div>

        @if ($seeAll)
            <a href="{{ $seeAll }}"
               class="block border-t border-border px-4 py-3 text-center text-sm font-semibold
                      text-primary transition-colors hover:bg-accent">
                See all notifications
            </a>
        @endif
    </div>
</div>
