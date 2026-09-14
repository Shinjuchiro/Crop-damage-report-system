@php
    // Freshly-submitted reports the office has not even assigned to a
    // technician yet - the clearest signal that "a farmer reported
    // something and nobody has looked at it." Shown as a badge on Crop
    // Damage Monitoring below instead of the bell, since MAO has no personal
    // notification inbox (its bell/"Notification and Alerts" page is the
    // alert-composer, not an inbox of incoming events) and a farmer's
    // submission is not something the office "sends" anyone.
    $pendingDamageReports = \App\Models\DamageReport::where('status', 'pending')->count();

    // Same idea, one stage further: a technician has already submitted an
    // inspection (status = verified) and it is sitting there waiting for
    // MAO's approve/flag/reject decision (DamageReportMonitorController::
    // decide()). Shown on Validation Monitoring, the screen that already
    // lists these.
    $verifiedAwaitingDecision = \App\Models\DamageReport::where('status', 'verified')->count();

    // route => null renders a disabled item, so the sidebar always shows the full
    // MAO menu even while a module is still being built.
    $items = [
        [
            'label'   => 'Executive Dashboard',
            'route'   => 'mao.dashboard',
            'pattern' => 'mao.dashboard',
            'icon'    => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9',
        ],
        [
            'label'   => 'Users Management',
            'route'   => 'mao.users.index',
            'pattern' => 'mao.users.*',
            'icon'    => 'M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9M16 2.7a4 4 0 010 7.6',
        ],
        [
            'label'   => 'Membership Applications',
            'route'   => 'mao.membership-applications.index',
            'pattern' => 'mao.membership-applications.*',
            'icon'    => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM8.5 14l2 2 4-4',
        ],
        [
            'label'   => "Farmer's Association",
            'route'   => 'mao.farmers.index',
            'pattern' => 'mao.farmers.*',
            'icon'    => 'M12 3a3 3 0 100 6 3 3 0 000-6zM5.5 21v-1.5a4 4 0 014-4h5a4 4 0 014 4V21M4 12.5a2 2 0 100-4 2 2 0 000 4zM20 12.5a2 2 0 100-4 2 2 0 000 4z',
        ],
        [
            'label'   => 'Crop Planting Monitoring',
            'route'   => 'mao.crop-planting.index',
            'pattern' => 'mao.crop-planting.*',
            'icon'    => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16',
        ],
        [
            'label'   => 'Crop Damage Monitoring',
            'route'   => 'mao.damage-reports.index',
            'pattern' => 'mao.damage-reports.*',
            'icon'    => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
            'badge'       => $pendingDamageReports,
            'badge_label' => 'awaiting assignment',
        ],
        [
            'label'   => 'Validation Monitoring',
            'route'   => 'mao.validations.index',
            'pattern' => 'mao.validations.*',
            'icon'    => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
            'badge'       => $verifiedAwaitingDecision,
            'badge_label' => 'awaiting your decision',
        ],
        [
            'label'    => 'Assistance Allocation & Tracking',
            // Matches every sub-page below, so the group opens itself on
            // whichever one is current, and also covers the standalone
            // create/show/disputes pages that are not in the submenu.
            'pattern'  => 'mao.assistance*',
            'icon'     => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
            'children' => [
                [
                    'label'   => 'Assistance Allocation',
                    'route'   => 'mao.assistance-allocations.index',
                    'pattern' => 'mao.assistance-allocations.index',
                ],
                [
                    'label'   => 'Allocation History',
                    'route'   => 'mao.assistance-allocations.history',
                    'pattern' => 'mao.assistance-allocations.history',
                ],
                [
                    'label'   => 'Distribution Tracking',
                    'route'   => 'mao.assistance-allocations.distribution-tracking',
                    'pattern' => 'mao.assistance-allocations.distribution-tracking',
                ],
            ],
        ],
        [
            'label'   => 'Maps and Visualization',
            'route'   => 'mao.map.index',
            'pattern' => 'mao.map.*',
            'icon'    => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7',
        ],
        [
            'label'   => 'Notification and Alerts',
            'route'   => 'mao.notifications.index',
            'pattern' => 'mao.notifications.*',
            'icon'    => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1',
        ],
        [
            'label'   => 'Reports',
            'route'   => 'mao.reports.index',
            'pattern' => 'mao.reports.*',
            'icon'    => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM9 17.5V14M12 17.5v-6M15 17.5v-3',
        ],
        [
            'label'   => 'Archive',
            'route'   => 'mao.archive.index',
            'pattern' => 'mao.archive.*',
            'icon'    => 'M3 6.5h18v3.5H3zM5 10v9a1 1 0 001 1h12a1 1 0 001-1v-9M9.5 14h5',
        ],
    ];

    $base       = 'flex items-center gap-3 rounded-lg px-3 py-2.5 transition';
    $idle       = $base . ' hover:translate-x-1 hover:bg-sidebar-accent';
    $current    = $base . ' bg-sidebar-primary font-medium text-sidebar-primary-foreground shadow-sm';
    $disabled   = $base . ' cursor-not-allowed opacity-45';

    $childBase  = 'block truncate rounded-lg px-3 py-2 text-sm transition';
    $childIdle  = $childBase . ' text-sidebar-foreground/80 hover:translate-x-1 hover:bg-sidebar-accent hover:text-sidebar-foreground';
    $childCurr  = $childBase . ' bg-sidebar-primary font-medium text-sidebar-primary-foreground shadow-sm';
@endphp

@foreach ($items as $item)
    @if (! empty($item['children']))
        {{--
            An expandable group, e.g. "Assistance Allocation & Tracking".
            Starts open whenever the current page is one of its own children,
            so a person landing on Allocation History never finds the group
            collapsed and has to go hunting for what they are already on.
        --}}
        <div x-data="{ open: {{ request()->routeIs($item['pattern']) ? 'true' : 'false' }} }">
            <button type="button" @click="open = ! open"
                    class="{{ request()->routeIs($item['pattern']) ? $current : $idle }} w-full min-w-0 justify-between">
                <span class="flex min-w-0 items-center gap-3">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="{{ $item['icon'] }}"/>
                    </svg>
                    <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition class="mt-1 space-y-0.5 pl-8">
                @foreach ($item['children'] as $child)
                    <a href="{{ route($child['route']) }}"
                       class="{{ request()->routeIs($child['pattern']) ? $childCurr : $childIdle }}">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @elseif ($item['route'])
        <a href="{{ route($item['route']) }}"
           class="{{ request()->routeIs($item['pattern']) ? $current : $idle }} min-w-0 justify-between">
            <span class="flex min-w-0 items-center gap-3">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="{{ $item['icon'] }}"/>
                </svg>
                <span class="truncate">{{ $item['label'] }}</span>
            </span>
            @if (! empty($item['badge']))
                <span class="ml-2 inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full
                             bg-destructive px-1.5 text-[11px] font-bold leading-none text-destructive-foreground"
                      title="{{ $item['badge'] }} report{{ $item['badge'] === 1 ? '' : 's' }} {{ $item['badge_label'] ?? 'needs attention' }}">
                    {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                </span>
            @endif
        </a>
    @else
        <span class="{{ $disabled }}" title="Coming in a later build step">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="{{ $item['icon'] }}"/>
            </svg>
            <span class="truncate">{{ $item['label'] }}</span>
        </span>
    @endif
@endforeach
