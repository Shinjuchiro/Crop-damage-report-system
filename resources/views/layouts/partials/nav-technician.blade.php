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

    // Reports MAO just handed this technician (status = assigned) that they
    // have not even opened/started yet (Start Inspection moves a report to
    // under_verification). This is the technician's equivalent of MAO's own
    // "new report" badge - a fresh assignment nobody has acted on.
    $newAssignments = \App\Models\DamageReport::where('assigned_technician_id', auth()->id())
        ->where('status', 'assigned')
        ->count();

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
            'badge'    => $newAssignments,
        ],
        [
            'label'    => 'Maps and Visualization',
            'filipino' => 'Mapa',
            'route'    => 'technician.map.index',
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
            'route'    => 'technician.summaries.index',
            'pattern'  => 'technician.summaries.*',
            'icon'     => 'M6 20V10M12 20V4M18 20v-6M3.5 20h17',
        ],
    ];

    // "Need Help?" is rendered on its own below a divider, after the loop,
    // set apart from the working menu above it - for every role.

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

        @if (! empty($item['badge']))
            <span class="ml-2 inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full
                         bg-destructive px-1.5 text-[11px] font-bold leading-none text-destructive-foreground"
                  title="{{ $item['badge'] }} new assignment{{ $item['badge'] === 1 ? '' : 's' }} not started yet">
                {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
            </span>
        @endif
    </{{ $item['route'] ? 'a' : 'span' }}>
@endforeach

{{-- Pinned to the very bottom of the sidebar: the nav element is a flex
     column (layouts/app.blade.php) and mt-auto here pushes this whole
     block - divider included - all the way down, not just after the
     last menu entry. --}}
<div class="mt-auto">
    <div class="my-2 border-t border-sidebar-border"></div>

    <a href="{{ route('help') }}" class="{{ request()->routeIs('help') ? $current : $idle }}">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 22a10 10 0 100-20 10 10 0 000 20zM9.5 9.5a2.5 2.5 0 113.2 2.4c-.5.2-.7.6-.7 1.1v.5m0 3h.01"/>
        </svg>

        <span class="min-w-0 flex-1 leading-tight">
            <span class="block truncate">Need Help?</span>
            <span class="block truncate text-xs opacity-70">Kailangan ng Tulong?</span>
        </span>
    </a>
</div>
