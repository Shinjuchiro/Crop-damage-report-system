@php
    // The farmer menu. Short on purpose: a farmer has four things to do here,
    // and burying them under a long list helps nobody.

    // Sept 2026 notification-system rule: sidebar badges show PENDING WORK
    // on the actual records, never a count of unread bell notifications -
    // that distinction matters because a farmer could read every bell
    // notification about a report and the badge should still show while the
    // report itself is sitting in a state that needs their attention.
    $farmerId = \App\Models\Farmer::where('user_id', auth()->id())->value('id');

    // A status the farmer hasn't necessarily "seen" play out yet: verified/
    // flagged/approved/rejected are all the office or the technician having
    // just moved the report somewhere new. Pending/assigned/under_verification
    // are just the normal wait and are not badged.
    $reportsNeedingAttention = $farmerId
        ? \App\Models\DamageReport::where('farmer_id', $farmerId)
            ->whereIn('status', ['verified', 'flagged', 'approved', 'rejected'])
            ->count()
        : 0;

    // Assistance the association has recorded giving out, that this farmer
    // hasn't yet confirmed receiving (see Farmer\AssistanceController).
    $assistanceAwaitingConfirmation = $farmerId
        ? \App\Models\AssistanceDistribution::where('farmer_id', $farmerId)
            ->where('receipt_status', 'pending_confirmation')
            ->count()
        : 0;

    $items = [
        [
            'label'   => 'Dashboard',
            'filipino'=> 'Dashboard',
            'route'   => 'farmer.dashboard',
            'pattern' => 'farmer.dashboard',
            'icon'    => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9',
        ],
        [
            'label'   => 'My Profile',
            'filipino'=> 'Aking Profile',
            'route'   => 'farmer.profile',
            'pattern' => 'farmer.profile',
            'icon'    => 'M16 19v-1.5a4 4 0 00-4-4H8a4 4 0 00-4 4V19M12 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z',
        ],
        [
            'label'   => 'Crop Planting',
            'filipino'=> 'Pagtatanim',
            'route'   => 'farmer.planting.index',
            'pattern' => 'farmer.planting.*',
            'icon'    => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16',
        ],
        [
            'label'   => 'Report Crop Damage',
            'filipino'=> 'Mag-ulat ng Pinsala',
            'route'   => 'farmer.reports.create',
            'pattern' => 'farmer.reports.create',
            'icon'    => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
        ],
        [
            'label'       => 'My Reports',
            'filipino'    => 'Aking mga Ulat',
            'route'       => 'farmer.reports.index',
            'pattern'     => 'farmer.reports.index',
            'icon'        => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
            'badge'       => $reportsNeedingAttention,
            'badge_label' => 'with a status update',
        ],
        [
            'label'       => 'Assistance',
            'filipino'    => 'Tulong',
            'route'       => 'farmer.assistance.index',
            'pattern'     => 'farmer.assistance.*',
            'icon'        => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
            'badge'       => $assistanceAwaitingConfirmation,
            'badge_noun'  => 'item',
            'badge_label' => 'awaiting your confirmation',
        ],
        [
            'label'   => 'Need Help?',
            'filipino'=> 'Kailangan ng Tulong?',
            'route'   => 'help',
            'pattern' => 'help',
            'icon'    => 'M12 22a10 10 0 100-20 10 10 0 000 20zM9.5 9.5a2.5 2.5 0 113.2 2.4c-.5.2-.7.6-.7 1.1v.5m0 3h.01',
        ],
    ];

    /*
     | Notifications is deliberately NOT in this list.
     |
     | The bell in the top bar already goes to farmer.notifications.index and
     | already carries the unread count, so a second door to the same page was
     | just making the menu longer. The route and the page are untouched.
     */

    $base     = 'flex items-center gap-3 rounded-lg px-3 py-3 transition';
    $idle     = $base . ' hover:translate-x-1 hover:bg-sidebar-accent';
    $current  = $base . ' bg-sidebar-primary font-medium text-sidebar-primary-foreground shadow-sm';
    $disabled = $base . ' cursor-not-allowed opacity-45';
@endphp

@foreach ($items as $item)
    @php $classes = $item['route'] ? (request()->routeIs($item['pattern']) ? $current : $idle) : $disabled; @endphp

    <{{ $item['route'] ? 'a' : 'span' }}
        @if ($item['route']) href="{{ route($item['route']) }}" @else title="Coming in a later build step" @endif
        class="{{ $classes }} justify-between">

        <span class="flex min-w-0 items-center gap-3">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="{{ $item['icon'] }}"/>
            </svg>

            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate">{{ $item['label'] }}</span>
                {{-- The Filipino line is the one most farmers will actually read --}}
                <span class="block truncate text-xs opacity-70">{{ $item['filipino'] }}</span>
            </span>
        </span>

        @if (! empty($item['badge']))
            <span class="ml-2 inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full
                         bg-destructive px-1.5 text-[11px] font-bold leading-none text-destructive-foreground"
                  title="{{ $item['badge'] }} {{ $item['badge_noun'] ?? 'report' }}{{ $item['badge'] === 1 ? '' : 's' }} {{ $item['badge_label'] ?? 'needs attention' }}">
                {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
            </span>
        @endif
    </{{ $item['route'] ? 'a' : 'span' }}>
@endforeach
