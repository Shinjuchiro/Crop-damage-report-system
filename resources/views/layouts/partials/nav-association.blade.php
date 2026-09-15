@php
    /*
     |--------------------------------------------------------------------
     | Farmers' Association sidebar
     |--------------------------------------------------------------------
     | Short on purpose. An officer does three things: look after their
     | members, watch what those members filed, and hand out the assistance
     | the office sent them.
     |
     | Settings is not in this list. The layout renders it on its own at the
     | bottom of the sidebar, below a divider, for every role.
     */
    $items = [
        [
            'label'    => 'Dashboard',
            'filipino' => 'Dashboard',
            'route'    => 'association.dashboard',
            'pattern'  => 'association.dashboard',
            'icon'     => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9',
        ],
        [
            'label'    => 'Members',
            'filipino' => 'Mga Kasapi',
            'route'    => 'association.members.index',
            'pattern'  => 'association.members.*',
            'icon'     => 'M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9',
        ],
        [
            'label'    => 'Planting Activities',
            'filipino' => 'Pagtatanim',
            'route'    => 'association.planting.index',
            'pattern'  => 'association.planting.*',
            'icon'     => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
        ],
        [
            'label'    => 'Damage Reports',
            'filipino' => 'Ulat ng Pinsala',
            'route'    => 'association.reports.index',
            'pattern'  => 'association.reports.*',
            'icon'     => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
        ],
        [
            'label'    => 'Assistance',
            'filipino' => 'Tulong',
            'route'    => 'association.assistance.index',
            'pattern'  => 'association.assistance.*',
            'icon'     => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
        ],
        [
            'label'    => 'Maps and Visualization',
            'filipino' => 'Mapa',
            'route'    => null,      // comes with a later build step
            'pattern'  => 'association.map.*',
            'icon'     => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7',
        ],
        [
            'label'    => 'Reports',
            'filipino' => 'Mga Ulat',
            'route'    => null,      // comes with a later build step
            'pattern'  => 'association.summaries.*',
            'icon'     => 'M6 20V10M12 20V4M18 20v-6M3.5 20h17',
        ],
        [
            'label'    => 'Need Help?',
            'filipino' => 'Kailangan ng Tulong?',
            'route'    => 'help',
            'pattern'  => 'help',
            'icon'     => 'M12 22a10 10 0 100-20 10 10 0 000 20zM9.5 9.5a2.5 2.5 0 113.2 2.4c-.5.2-.7.6-.7 1.1v.5m0 3h.01',
        ],
    ];

    $base     = 'flex items-center gap-3 rounded-lg px-3 py-2.5 transition';
    $idle     = $base . ' hover:translate-x-1 hover:bg-sidebar-accent';
    $current  = $base . ' bg-sidebar-primary font-medium text-sidebar-primary-foreground shadow-sm';
    $disabled = $base . ' cursor-not-allowed opacity-45';
@endphp

@foreach ($items as $item)
    @php $classes = $item['route'] ? (request()->routeIs($item['pattern']) ? $current : $idle) : $disabled; @endphp

    <{{ $item['route'] ? 'a' : 'span' }}
        @if ($item['route']) href="{{ route($item['route']) }}" @else title="Coming in a later build step" @endif
        class="{{ $classes }}">

        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="{{ $item['icon'] }}"/>
        </svg>

        <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate">{{ $item['label'] }}</span>
            <span class="block truncate text-xs opacity-70">{{ $item['filipino'] }}</span>
        </span>
    </{{ $item['route'] ? 'a' : 'span' }}>
@endforeach
