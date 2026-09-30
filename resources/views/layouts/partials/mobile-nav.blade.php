@php
    /*
     |--------------------------------------------------------------------
     | Bottom navigation (phones and tablets)
     |--------------------------------------------------------------------
     | A floating bar rather than a band welded to the bottom edge: inset
     | from the sides, rounded, with a shadow under it, and the active tab
     | marked by a filled green circle behind its icon. That is the shape in
     | the reference design, and it is most of what makes a web page read as
     | an app on a phone.
     |
     | FOUR TABS AND A MORE SHEET
     |
     | The four tabs are each role's own most-used screens, so a farmer has
     | Report one tap away and a technician has Validation. Everything else
     | the role can reach lives in the More sheet, which is this bar's
     | equivalent of the sidebar: the same destinations, as large tap
     | targets instead of a dense list.
     |
     | The sheet's contents follow the AniAgapay screens role for role, with
     | two deliberate departures, both because the page does not exist:
     | the association has no Announcements screen of its own (the office
     | sends them; the association reads them under Notifications), and no
     | role has a screen behind a row that would only lead nowhere.
     |
     | Settings sits in its own group under a gap, away from the rest, so a
     | thumb reaching for the last item in the list cannot land on it.
     |
     | Hidden on lg and up, where the sidebar takes over.
     */

    $role = auth()->user()->role;

    // Icon paths in one place, so a tab and its row in the sheet can never
    // drift apart.
    $ico = [
        'home'     => 'M3 11l9-7 9 7M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9',
        'planting' => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
        'reports'  => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
        'damage'   => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
        'report'   => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'check'    => 'M12 22a10 10 0 100-20 10 10 0 000 20zM8.5 12.2l2.4 2.4 4.6-4.8',
        'clock'    => 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 6.5V12l3.5 2.2',
        'people'   => 'M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9',
        'assist'   => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
        'map'      => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7',
        'bell'     => 'M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0',
        'user'     => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
        'help'     => 'M12 22a10 10 0 100-20 10 10 0 000 20zM9.1 9a3 3 0 015.8 1c0 2-3 3-3 3M12 17h.01',
        'cog'      => 'M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM4.3 15.5a1.7 1.7 0 00-.4 1.9l.1.1a2 2 0 102.8 2.8l.1-.1a1.7 1.7 0 011.9-.4 1.7 1.7 0 011 1.6V21a2 2 0 104 0v-.1a1.7 1.7 0 011-1.6 1.7 1.7 0 011.9.4l.1.1a2 2 0 102.8-2.8l-.1-.1a1.7 1.7 0 01-.4-1.9 1.7 1.7 0 011.6-1H21a2 2 0 100-4h-.1a1.7 1.7 0 01-1.6-1 1.7 1.7 0 01.4-1.9l.1-.1a2 2 0 10-2.8-2.8l-.1.1a1.7 1.7 0 01-1.9.4 1.7 1.7 0 01-1-1.6V3a2 2 0 10-4 0v.1a1.7 1.7 0 01-1 1.6 1.7 1.7 0 01-1.9-.4l-.1-.1a2 2 0 10-2.8 2.8l.1.1a1.7 1.7 0 01.4 1.9 1.7 1.7 0 01-1.6 1H3a2 2 0 100 4h.1a1.7 1.7 0 011.6 1z',
        'archive'  => 'M21 8v12a1 1 0 01-1 1H4a1 1 0 01-1-1V8M2 4h20v4H2zM10 12h4',
        'chart'    => 'M3 3v18h18M7 15l3-3 3 3 5-6',
        'leaf'     => 'M4 20c0-9 5-14 16-15 0 11-5 16-14 16H4zM4 20c1-5 4-8 8-9.5',
        'megaphone'=> 'M3 11a1 1 0 011-1h2.5L15 5.5v13L6.5 14H4a1 1 0 01-1-1v-2zM7.5 14.2V19a1 1 0 001 1h1.5a1 1 0 001-1v-3.3M18.5 9a3.5 3.5 0 010 6',
    ];

    /*
     | Per role: four tabs, the rows the More sheet holds, and Settings on
     | its own. Plain data, so a route rename is one line rather than a hunt
     | through markup.
     */
    $sets = [
        'farmer' => [
            'tabs' => [
                ['label' => 'Home',     'route' => 'farmer.dashboard',      'pattern' => 'farmer.dashboard',      'icon' => $ico['home']],
                ['label' => 'Planting', 'route' => 'farmer.planting.index', 'pattern' => 'farmer.planting.*',     'icon' => $ico['planting']],
                /* "History", not "Reports": the tab beside it is also called
                   Report, and two tabs a letter apart is not a choice a
                   farmer should have to read twice. This one is the list of
                   what they have already filed, so it takes the clock, the
                   same icon the technician's History tab uses. */
                ['label' => 'History',  'route' => 'farmer.reports.index',  'pattern' => 'farmer.reports.index',  'icon' => $ico['clock']],
                ['label' => 'Report',   'route' => 'farmer.reports.create', 'pattern' => 'farmer.reports.create', 'icon' => $ico['report']],
            ],
            'more' => [
                ['label' => 'My Profile',             'fil' => 'Aking Profile',       'route' => 'farmer.profile',             'icon' => $ico['user']],
                ['label' => 'My Crop Planting',       'fil' => 'Aking Pagtatanim',    'route' => 'farmer.planting.index',      'icon' => $ico['planting']],
                ['label' => 'My Reports',             'fil' => 'Aking Mga Ulat',      'route' => 'farmer.reports.index',       'icon' => $ico['reports']],
                ['label' => 'Assistance',             'fil' => 'Tulong',              'route' => 'farmer.assistance.index',    'icon' => $ico['assist']],
                ['label' => 'Notifications & Alerts', 'fil' => 'Mga Abiso',           'route' => 'farmer.notifications.index', 'icon' => $ico['bell']],
                ['label' => 'Need Help',              'fil' => 'Kailangan ng Tulong', 'route' => 'help',                       'icon' => $ico['help']],
            ],
            'settings' => [
                ['label' => 'Settings', 'fil' => 'Mga Setting', 'route' => 'farmer.settings.index', 'icon' => $ico['cog']],
            ],
        ],

        'technician' => [
            'tabs' => [
                ['label' => 'Home',        'route' => 'technician.dashboard',        'pattern' => 'technician.dashboard',    'icon' => $ico['home']],
                ['label' => 'Assignments', 'route' => 'technician.reports.index',    'pattern' => 'technician.reports.*',    'icon' => $ico['reports']],
                ['label' => 'Validation',  'route' => 'technician.validation.index', 'pattern' => 'technician.validation.*', 'icon' => $ico['check']],
                ['label' => 'History',     'route' => 'technician.history.index',    'pattern' => 'technician.history.*',    'icon' => $ico['clock']],
            ],
            'more' => [
                ['label' => 'Assigned Reports',       'fil' => 'Mga Nakatalagang Ulat', 'route' => 'technician.reports.index',       'icon' => $ico['reports']],
                ['label' => 'Planting Reports',       'fil' => 'Ulat ng Pagtatanim',    'route' => 'technician.planting.index',      'icon' => $ico['planting']],
                ['label' => 'Validation',             'fil' => 'Pagsusuri',             'route' => 'technician.validation.index',    'icon' => $ico['check']],
                ['label' => 'Maps & Visualization',   'fil' => 'Mapa',                  'route' => 'technician.map.index',           'icon' => $ico['map']],
                ['label' => 'Inspection History',     'fil' => 'Kasaysayan ng Inspeksyon', 'route' => 'technician.history.index',    'icon' => $ico['clock']],
                /* Not on the reference sheet, kept because the sheet is the
                   only navigation below 1024px now and the page would
                   otherwise have no door on a phone at all. */
                ['label' => 'Summaries',              'fil' => 'Buod',                  'route' => 'technician.summaries.index',     'icon' => $ico['chart']],
                ['label' => 'Notifications & Alerts', 'fil' => 'Mga Abiso',             'route' => 'technician.notifications.index', 'icon' => $ico['bell']],
                ['label' => 'Need Help',              'fil' => 'Kailangan ng Tulong',   'route' => 'help',                           'icon' => $ico['help']],
            ],
            'settings' => [
                ['label' => 'Settings', 'fil' => 'Mga Setting', 'route' => 'technician.settings.index', 'icon' => $ico['cog']],
            ],
        ],

        'association' => [
            'tabs' => [
                ['label' => 'Home',       'route' => 'association.dashboard',        'pattern' => 'association.dashboard',    'icon' => $ico['home']],
                ['label' => 'Members',    'route' => 'association.members.index',    'pattern' => 'association.members.*',    'icon' => $ico['people']],
                ['label' => 'Reports',    'route' => 'association.reports.index',    'pattern' => 'association.reports.*',    'icon' => $ico['damage']],
                ['label' => 'Assistance', 'route' => 'association.assistance.index', 'pattern' => 'association.assistance.*', 'icon' => $ico['assist']],
            ],
            'more' => [
                ['label' => 'Members',                'fil' => 'Mga Kasapi',          'route' => 'association.members.index',       'icon' => $ico['people']],
                ['label' => 'Planting Activities',    'fil' => 'Mga Pagtatanim',      'route' => 'association.planting.index',      'icon' => $ico['planting']],
                ['label' => 'Damage Reports',         'fil' => 'Ulat ng Pinsala',     'route' => 'association.reports.index',       'icon' => $ico['damage']],
                ['label' => 'Assistance',             'fil' => 'Tulong',              'route' => 'association.assistance.index',    'icon' => $ico['assist']],
                ['label' => 'Maps & Visualization',   'fil' => 'Mapa',                'route' => 'association.map.index',           'icon' => $ico['map']],
                ['label' => 'Summaries',              'fil' => 'Buod',                'route' => 'association.summaries.index',     'icon' => $ico['chart']],
                ['label' => 'Notifications & Alerts', 'fil' => 'Mga Abiso',           'route' => 'association.notifications.index', 'icon' => $ico['bell']],
                ['label' => 'Need Help',              'fil' => 'Kailangan ng Tulong', 'route' => 'help',                            'icon' => $ico['help']],
            ],
            'settings' => [
                ['label' => 'Settings', 'fil' => 'Mga Setting', 'route' => 'association.settings.index', 'icon' => $ico['cog']],
            ],
        ],

        'mao' => [
            'tabs' => [
                ['label' => 'Home',    'route' => 'mao.dashboard',            'pattern' => 'mao.dashboard',        'icon' => $ico['home']],
                ['label' => 'Farmers', 'route' => 'mao.farmers.index',        'pattern' => 'mao.farmers.*',        'icon' => $ico['people']],
                ['label' => 'Reports', 'route' => 'mao.damage-reports.index', 'pattern' => 'mao.damage-reports.*', 'icon' => $ico['damage']],
                ['label' => 'Map',     'route' => 'mao.map.index',            'pattern' => 'mao.map.*',            'icon' => $ico['map']],
            ],
            'more' => [
                ['label' => 'User Management',         'fil' => 'Mga Gumagamit',      'route' => 'mao.users.index',                   'icon' => $ico['people']],
                ['label' => 'Membership Applications', 'fil' => 'Mga Aplikasyon',     'route' => 'mao.membership-applications.index', 'icon' => $ico['reports']],
                ["label" => "Farmers' Associations",   'fil' => 'Mga Samahan',        'route' => 'mao.associations.index',            'icon' => $ico['leaf']],
                ['label' => 'Crop Planting Monitoring','fil' => 'Pagsubaybay sa Pagtatanim', 'route' => 'mao.crop-planting.index',    'icon' => $ico['planting']],
                ['label' => 'Crop Damage Monitoring',  'fil' => 'Pagsubaybay sa Pinsala',    'route' => 'mao.damage-reports.index',   'icon' => $ico['damage']],
                ['label' => 'Validation',              'fil' => 'Pagsusuri',          'route' => 'mao.validations.index',             'icon' => $ico['check']],
                ['label' => 'Assistance Allocation',   'fil' => 'Paglalaan ng Tulong','route' => 'mao.assistance-allocations.index',  'icon' => $ico['assist']],
                /* Also not on the reference sheet. Unlike Allocation History,
                   which the allocations page links to, nothing else in the
                   system links here, so without this row the page has no
                   door at all below 1024px. */
                ['label' => 'Distribution Tracking',   'fil' => 'Pagsubaybay sa Pamamahagi', 'route' => 'mao.assistance-allocations.distribution-tracking', 'icon' => $ico['check']],
                ['label' => 'Maps & Visualization',    'fil' => 'Mapa',               'route' => 'mao.map.index',                     'icon' => $ico['map']],
                /* "Announcements", not "Notifications": this is where the
                   office WRITES and reviews what it has sent. What the
                   office has been sent is the bell in the top bar, a
                   different page (see the note in layouts/app.blade.php). */
                ['label' => 'Announcements',           'fil' => 'Mga Anunsyo',        'route' => 'mao.notifications.index',           'icon' => $ico['megaphone']],
                ['label' => 'Reports',                 'fil' => 'Mga Ulat',           'route' => 'mao.reports.index',                 'icon' => $ico['chart']],
                ['label' => 'Archive',                 'fil' => 'Archive',            'route' => 'mao.archive.index',                 'icon' => $ico['archive']],
                ['label' => 'Need Help',               'fil' => 'Kailangan ng Tulong','route' => 'help',                              'icon' => $ico['help']],
            ],
            'settings' => [
                ['label' => 'Settings', 'fil' => 'Mga Setting', 'route' => 'mao.settings', 'icon' => $ico['cog']],
            ],
        ],
    ];

    // A role whose module is not built yet still gets Home, and a sheet with
    // the things every role has.
    $set = $sets[$role] ?? [
        'tabs' => [['label' => 'Home', 'route' => $role . '.dashboard', 'pattern' => $role . '.dashboard', 'icon' => $ico['home']]],
        'more' => [['label' => 'Need Help', 'fil' => 'Kailangan ng Tulong', 'route' => 'help', 'icon' => $ico['help']]],
        'settings' => [],
    ];

    /* Drop any row whose route does not exist rather than letting one
       missing module take the whole bar down with a RouteNotFoundException.
       Cheap insurance: this partial renders on every page a phone loads.

       It is insurance, not a strategy: a row silently vanishing is exactly
       how Settings went missing from three of the four roles for a while,
       under a route name that had never existed. Every name above is
       checked against routes/web.php. */
    $keep = fn ($rows) => array_values(array_filter(
        $rows ?? [],
        fn ($item) => \Illuminate\Support\Facades\Route::has($item['route'])
    ));

    $more     = $keep($set['more'] ?? []);
    $settings = $keep($set['settings'] ?? []);
@endphp

<div x-data="{ sheet: false }" class="lg:hidden">

    {{-- =====================================================================
         THE BAR
    ====================================================================== --}}
    <nav class="fixed inset-x-3 bottom-3 z-30 rounded-3xl border border-border bg-card px-1 py-1.5 shadow-lg"
         style="margin-bottom: env(safe-area-inset-bottom)"
         aria-label="Main navigation">

        <div class="mx-auto flex max-w-lg items-stretch">

            @foreach ($set['tabs'] as $tab)
                @php $active = request()->routeIs($tab['pattern']); @endphp

                <a href="{{ route($tab['route']) }}"
                   @if ($active) aria-current="page" @endif
                   class="flex flex-1 flex-col items-center justify-center gap-1 rounded-2xl py-1.5 text-center">

                    <span class="flex h-9 w-9 items-center justify-center rounded-full transition-colors
                                 {{ $active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="{{ $active ? '2.1' : '1.7' }}"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="{{ $tab['icon'] }}"/>
                        </svg>
                    </span>

                    <span class="text-[11px] leading-none {{ $active ? 'font-semibold text-primary' : 'font-medium text-muted-foreground' }}">
                        {{ $tab['label'] }}
                    </span>
                </a>
            @endforeach

            {{-- More. A button, not a link: it opens the sheet below. --}}
            @if (count($more) || count($settings))
                <button type="button" @click="sheet = true"
                        class="flex flex-1 flex-col items-center justify-center gap-1 rounded-2xl py-1.5 text-center"
                        aria-haspopup="dialog" :aria-expanded="sheet">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full transition-colors"
                          :class="sheet ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>
                        </svg>
                    </span>
                    <span class="text-[11px] leading-none"
                          :class="sheet ? 'font-semibold text-primary' : 'font-medium text-muted-foreground'">More</span>
                </button>
            @endif
        </div>
    </nav>

    {{-- =====================================================================
         THE SHEET
         Rises from the bottom over a dimmed page. The backdrop, the X and
         the escape key all close it.
    ====================================================================== --}}
    <div x-show="sheet" x-cloak @keydown.escape.window="sheet = false" class="fixed inset-0 z-40">

        <div x-show="sheet" x-transition.opacity
             class="absolute inset-0 bg-foreground/40" @click="sheet = false"></div>

        <div x-show="sheet"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-card px-4
                    pb-[calc(1.25rem+env(safe-area-inset-bottom))] pt-3 shadow-lg"
             role="dialog" aria-modal="true" aria-label="More">

            {{-- The grab handle. Decoration, but it is what tells a thumb the
                 panel came up from the bottom edge. --}}
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-border" aria-hidden="true"></div>

            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-xl font-bold text-foreground">More</h2>
                <button type="button" @click="sheet = false" aria-label="Close"
                        class="rounded-full p-2 text-muted-foreground hover:bg-muted">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            {{-- One grouped card with hairlines between the rows, rather than
                 a stack of separate cards: the list reads as one thing, and
                 eleven separate cards on the office's sheet was a lot of
                 border for a phone screen. --}}
            @if (count($more))
                <ul class="divide-y divide-border overflow-hidden rounded-2xl border border-border">
                    @foreach ($more as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 bg-card p-3 transition active:bg-muted">

                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                                      aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="{{ $item['icon'] }}"/>
                                    </svg>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-base font-semibold text-foreground">{{ $item['label'] }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ $item['fil'] }}</span>
                                </span>

                                <svg class="h-5 w-5 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 6l6 6-6 6"/>
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Settings, set apart. It is the one row here that changes the
                 account rather than going somewhere, so it does not sit at
                 the end of the list where a thumb scrolling to the bottom
                 lands on it. --}}
            @if (count($settings))
                <ul class="mt-4 divide-y divide-border overflow-hidden rounded-2xl border border-border">
                    @foreach ($settings as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 bg-card p-3 transition active:bg-muted">

                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent text-primary"
                                      aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="{{ $item['icon'] }}"/>
                                    </svg>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-base font-semibold text-foreground">{{ $item['label'] }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ $item['fil'] }}</span>
                                </span>

                                <svg class="h-5 w-5 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M9 6l6 6-6 6"/>
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
