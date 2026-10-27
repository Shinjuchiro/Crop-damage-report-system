{{--
    ONE NOTIFICATION, IN THE SHAPE PEOPLE ALREADY KNOW

        <x-notification.row :notification="$notification" :href="$url" />

    The layout is the one every phone in Tanza already has on it: a round
    icon on the left, the message in the middle with the title in bold, a
    short relative time at the end of it, and a dot on the right while it is
    unread. Nobody has to learn it.

    Used by the bell's dropdown panel and by all four roles' notification
    pages, so the same row cannot read one way in the panel and another on
    the page.

    THE CIRCLE SAYS WHAT KIND OF THING THIS IS

    Facebook's circle is a photograph of the person who caused the
    notification. Almost nothing here is caused by a person: it is the
    office announcing, or the system reporting that an inspection finished.
    A photograph would be the same seal on every row. So the circle carries
    the KIND instead - an amber warning for a disaster advisory, a green
    leaf for a farming reminder, a calendar for a meeting - which is the one
    thing a reader wants before they decide whether to open it.

    Urgent and Critical get a small red mark on the corner of that circle,
    the way a message app marks the one row that cannot wait.

    props
      notification  a Notification row, with its broadcast loaded
      href          where the row goes; null renders it as a plain block
      compact       tighter padding and a shorter message, for the dropdown
      stamp         show the clock time or date instead of the short "2d"
--}}

@props([
    'notification',
    'href'    => null,
    'compact' => false,
    // On the full page the time is the clock time or date, as the page's
    // Today / Yesterday headings expect. The dropdown keeps the short "2d".
    'stamp'   => false,
])

@php
    $alert = $notification->broadcast;

    /* Tint, icon colour and drawing per category. Kept here rather than in
       the model: these are presentation, and the model has no business
       knowing about amber. */
    $kinds = [
        'disaster_alert' => [
            'tint' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            'icon' => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        ],
        'agricultural_alert' => [
            'tint' => 'bg-accent text-primary',
            'icon' => 'M4 20c0-9 5-14 16-15 0 11-5 16-14 16H4zM4 20c1-5 4-8 8-9.5',
        ],
        'event' => [
            'tint' => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
            'icon' => 'M8 3v4M16 3v4M3.5 9.5h17M5 5.5h14a1.5 1.5 0 011.5 1.5v12A1.5 1.5 0 0119 20.5H5A1.5 1.5 0 013.5 19V7A1.5 1.5 0 015 5.5z',
        ],
        'system' => [
            'tint' => 'bg-secondary text-secondary-foreground',
            'icon' => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
        ],
        'assistance' => [
            'tint' => 'bg-lime-100 text-lime-700 dark:bg-lime-950 dark:text-lime-300',
            'icon' => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
        ],
        'announcement' => [
            'tint' => 'bg-accent text-primary',
            'icon' => 'M3 11a1 1 0 011-1h2.5L15 5.5v13L6.5 14H4a1 1 0 01-1-1v-2zM7.5 14.2V19a1 1 0 001 1h1.5a1 1 0 001-1v-3.3M18.5 9a3.5 3.5 0 010 6',
        ],
    ];

    $kind = $kinds[$alert?->category] ?? $kinds['announcement'];

    $urgent = in_array($alert?->priority, ['urgent', 'critical'], true);

    /* "2d", "1w", "5m" - the one line of this row that is pure Facebook.
       DIFF_ABSOLUTE drops the "ago": in a list that is only ever the past,
       the word is on every row and tells nobody anything. */
    $when = $notification->created_at?->diffForHumans([
        'short'  => true,
        'parts'  => 1,
        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
    ]);

    if ($stamp && $notification->created_at) {
        $at = $notification->created_at;

        $when = match (true) {
            $at->isToday()     => $at->format('g:i A'),
            $at->isYesterday() => 'Yesterday, ' . $at->format('g:i A'),
            default            => $at->format('F j, Y'),
        };
    }

    $unread = ! $notification->is_read;

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'flex w-full items-start gap-3 transition-colors',
        'px-3 py-2.5'   => $compact,
        'px-4 py-3.5'   => ! $compact,
        'hover:bg-muted/60' => (bool) $href,
        // Unread rows carry a faint wash as well as the dot, the same way
        // the apps people already use mark them. One signal is easy to miss
        // at a glance down a long list.
        'bg-primary/5'  => $unread,
    ]) }}>

    {{-- The circle --}}
    <span class="relative shrink-0">
        <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $kind['tint'] }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="{{ $kind['icon'] }}"/>
            </svg>
        </span>

        @if ($urgent)
            <span class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center
                         rounded-full bg-destructive text-[9px] font-bold text-destructive-foreground
                         ring-2 ring-card"
                  title="{{ $alert?->priority_label }}">!</span>
        @endif
    </span>

    {{-- The message. The title is bold and the body follows it in the same
         paragraph, which is what makes a Facebook row read as a sentence
         rather than as a heading with a caption under it. --}}
    <span class="min-w-0 flex-1">
        <span class="block {{ $compact ? 'text-sm' : 'text-sm sm:text-[0.95rem]' }} leading-snug text-card-foreground">
            <span class="font-semibold">{{ $alert?->title ?? 'Notification' }}</span>
            @if ($alert?->message)
                <span class="{{ $unread ? 'text-card-foreground/80' : 'text-muted-foreground' }}">
                    {{ \Illuminate\Support\Str::limit($alert->message, $compact ? 90 : 160) }}
                </span>
            @endif
        </span>

        <span class="mt-1 flex flex-wrap items-center gap-x-2 text-xs">
            <span class="{{ $unread ? 'font-semibold text-primary' : 'text-muted-foreground' }}">{{ $when }}</span>
            <span class="text-muted-foreground">&middot;</span>
            <span class="text-muted-foreground">{{ $alert?->category_label }}</span>
        </span>
    </span>

    {{-- The unread dot, on the right, where it does not push the text
         around when it disappears. --}}
    <span class="mt-3 h-2.5 w-2.5 shrink-0 rounded-full {{ $unread ? 'bg-primary' : 'bg-transparent' }}"
          @if ($unread) aria-label="Unread" @else aria-hidden="true" @endif></span>
</{{ $tag }}>
