@php
    /*
     |--------------------------------------------------------------------
     | Technician sidebar
     |--------------------------------------------------------------------
     | Laid out to match the approved mockup. Items with a route are live;
     | items with 'route' => null are greyed out and are the next build
     | steps. They are shown rather than hidden on purpose, so the panel
     | can see the shape of the finished module.
     |
     | Settings is not in this list. The layout renders it on its own at the
     | very bottom of the sidebar, below a divider, for every role.
     */
    $items = [
        [
            'label'    => 'Technical Dashboard',
            'filipino' => 'Dashboard',
            'route'    => 'technician.dashboard',
            'pattern'  => 'technician.dashboard',
            'icon'     => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9',
        ],
        [
            'label'    => 'Assigned Reports',
            'filipino' => 'Mga Nakatalaga',
            'route'    => 'technician.reports.index',
            'pattern'  => 'technician.reports.*',
            'icon'     => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
        ],
        [
            'label'    => 'Planting Reports',
            'filipino' => 'Ulat sa Pagtatanim',
            'route'    => null,
            'pattern'  => 'technician.planting.*',
            'icon'     => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
        ],
        [
            'label'    => 'Damage Reports',
            'filipino' => 'Ulat ng Pinsala',
            'route'    => null,
            'pattern'  => 'technician.damage.*',
            'icon'     => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
        ],
        [
            'label'    => 'Validation',
            'filipino' => 'Pagpapatunay',
            'route'    => 'technician.validation.index',
            'pattern'  => 'technician.validation.*',
            'icon'     => 'M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8',
        ],
        [
            'label'    => 'Maps and Visualization',
            'filipino' => 'Mapa',
            'route'    => null,
            'pattern'  => 'technician.map.*',
            'icon'     => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7',
        ],
        [
            'label'    => 'Inspection History',
            'filipino' => 'Kasaysayan',
            'route'    => 'technician.history.index',
            'pattern'  => 'technician.history.*',
            'icon'     => 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2',
        ],
        [
            'label'    => 'Reports',
            'filipino' => 'Mga Ulat',
            'route'    => null,
            'pattern'  => 'technician.summaries.*',
            'icon'     => 'M6 20V10M12 20V4M18 20v-6M3.5 20h17',
        ],
        [
            'label'    => 'Archive',
            'filipino' => 'Imbakan',
            'route'    => null,
            'pattern'  => 'technician.archive.*',
            'icon'     => 'M3 7h18v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l1.2-2.4A1 1 0 015.1 4h13.8a1 1 0 01.9.6L21 7M10 12h4',
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
