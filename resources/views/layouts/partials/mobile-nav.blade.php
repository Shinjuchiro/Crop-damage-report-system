@php
    /*
     |--------------------------------------------------------------------
     | Bottom navigation bar (phones only)
     |--------------------------------------------------------------------
     | On a phone the sidebar is hidden, so this is how people move around:
     | four flat tabs plus a fifth "More" tab that opens the same sidebar
     | drawer the header hamburger opens. Nothing in the menu becomes
     | unreachable on a phone, we just put the four most used pages up
     | front, one row, all the same size - no raised centre button.
     |
     | Each role's 4th tab is the one thing that role does most (Farmer:
     | report damage, Association: assistance, MAO: the map). Technician
     | has no single "most used" action beyond the three list pages it
     | already needs, so its 4th tab is History.
     |
     | Hidden on lg and up (lg:hidden) since the sidebar takes over there.
     */

    $role = auth()->user()->role;

    $sets = [
        'farmer' => [
            ['label' => 'Home',       'fil' => 'Home',   'route' => 'farmer.dashboard',      'pattern' => 'farmer.dashboard',    'icon' => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
            ['label' => 'Planting',   'fil' => 'Tanim',  'route' => 'farmer.planting.index',  'pattern' => 'farmer.planting.*',   'icon' => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z'],
            ['label' => 'My Reports', 'fil' => 'Ulat',   'route' => 'farmer.reports.index',   'pattern' => 'farmer.reports.index','icon' => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4'],
            ['label' => 'Report',     'fil' => 'Iulat',  'route' => 'farmer.reports.create',  'pattern' => 'farmer.reports.create','icon' => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
        ],

        'technician' => [
            ['label' => 'Home',        'fil' => 'Dashboard',  'route' => 'technician.dashboard',        'pattern' => 'technician.dashboard',    'icon' => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
            ['label' => 'Assignments', 'fil' => 'Nakatalaga', 'route' => 'technician.reports.index',    'pattern' => 'technician.reports.*',    'icon' => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4'],
            ['label' => 'Validation',  'fil' => 'Pagsusuri',  'route' => 'technician.validation.index', 'pattern' => 'technician.validation.*', 'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8'],
            ['label' => 'History',     'fil' => 'Kasaysayan', 'route' => 'technician.history.index',    'pattern' => 'technician.history.*',    'icon' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2'],
        ],

        'association' => [
            ['label' => 'Home',       'fil' => 'Dashboard',  'route' => 'association.dashboard',     'pattern' => 'association.dashboard',    'icon' => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
            ['label' => 'Members',    'fil' => 'Kasapi',     'route' => 'association.members.index', 'pattern' => 'association.members.*',    'icon' => 'M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9'],
            ['label' => 'Reports',    'fil' => 'Pinsala',    'route' => 'association.reports.index', 'pattern' => 'association.reports.*',    'icon' => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01'],
            ['label' => 'Assistance', 'fil' => 'Tulong',     'route' => 'association.assistance.index','pattern' => 'association.assistance.*','icon' => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21'],
        ],

        'mao' => [
            ['label' => 'Home',    'fil' => 'Dashboard', 'route' => 'mao.dashboard',            'pattern' => 'mao.dashboard',        'icon' => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
            ['label' => 'Farmers', 'fil' => 'Magsasaka', 'route' => 'mao.farmers.index',        'pattern' => 'mao.farmers.*',        'icon' => 'M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9'],
            ['label' => 'Reports', 'fil' => 'Pinsala',   'route' => 'mao.damage-reports.index',  'pattern' => 'mao.damage-reports.*', 'icon' => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01'],
            ['label' => 'Map',     'fil' => 'Mapa',      'route' => 'mao.map.index',             'pattern' => 'mao.map.*',            'icon' => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7'],
        ],
    ];

    // Roles whose module is not built yet just get Home plus More.
    $tabs = $sets[$role] ?? [
        ['label' => 'Home', 'fil' => 'Home', 'route' => $role . '.dashboard', 'pattern' => $role . '.dashboard', 'icon' => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
    ];

    $unread = \App\Models\Notification::where('user_id', auth()->id())
        ->where('is_read', false)
        ->count();
@endphp

{{-- pb-[env(safe-area-inset-bottom)] keeps the bar clear of the home
     indicator bar on iPhones and gesture bars on Android. --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card
            pb-[env(safe-area-inset-bottom)] shadow-[0_-2px_12px_-6px_rgb(15_23_42/0.25)]
            lg:hidden"
     aria-label="Main navigation">

    <div class="mx-auto flex h-16 max-w-lg items-stretch">

        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['pattern']); @endphp

            <a href="{{ route($tab['route']) }}"
               @if ($active) aria-current="page" @endif
               class="flex flex-1 flex-col items-center justify-center gap-0.5 text-center
                      {{ $active ? 'text-primary' : 'text-muted-foreground' }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="{{ $active ? '2.1' : '1.7' }}"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="{{ $tab['icon'] }}"/>
                </svg>
                <span class="text-[11px] leading-none {{ $active ? 'font-semibold' : 'font-medium' }}">
                    {{ $tab['label'] }}
                </span>
            </a>
        @endforeach

        {{-- "More" opens the same sidebar drawer the hamburger opens, so
             nothing in the menu becomes unreachable on a phone. --}}
        <button type="button" @click="sidebarOpen = true"
                class="relative flex flex-1 flex-col items-center justify-center gap-0.5 text-muted-foreground">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>
            </svg>
            <span class="text-[11px] font-medium leading-none">More</span>

            @if ($unread > 0)
                <span class="absolute right-1/2 top-2 translate-x-4 rounded-full bg-destructive px-1.5
                             text-[10px] font-bold leading-4 text-destructive-foreground">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
            @endif
        </button>
    </div>
</nav>
