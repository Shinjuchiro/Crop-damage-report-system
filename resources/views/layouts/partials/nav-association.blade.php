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
    /* Sept 2026 notification-system rule: a sidebar badge here represents
       pending work the association itself needs to act on, never a count of
       unread bell notifications. Per that rule, Members and Damage Reports
       stay unbadged - an officer only ever MONITORS those (proposal section
       61).

       WHAT THE ASSISTANCE BADGE COUNTS NOW (Oct 2026)

       It used to count allocations sent here with nothing handed out yet,
       plus members who said they had not received something. Both were work
       for the officer because the association did the distributing. It does
       not any more: the Municipal Agriculture Office hands assistance to
       the farmer and records it.

       What IS still the officer's job, and is the reason the office asked
       for the notice at all, is telling their selected members to come and
       collect. So the badge counts exactly that: members of this
       association designated on a live allocation who have not yet been
       handed theirs. It falls to zero when every one of them has collected,
       which is what a queue badge has to do. */
    $associationId = \App\Models\AssociationOfficer::where('user_id', auth()->id())->value('association_id');

    $assistanceNeedingAction = $associationId
        ? \App\Models\AssistanceAllocationBeneficiary::query()
            ->whereHas('allocation', fn ($allocation) => $allocation
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->whereHas('farmer', fn ($farmer) => $farmer->where('association_id', $associationId))
            /* Not yet handed theirs: no distribution row pairing this
               farmer with this allocation.

               Written as an explicit NOT EXISTS rather than
               whereDoesntHave('allocation.distributions', ...). That would
               have been a correlated subquery three relations deep, with
               the outer beneficiaries table referenced from inside two
               levels of nesting - legal SQL, but the kind of query whose
               correctness depends on exactly how the query builder names
               and scopes each level. This one is two columns matched
               against the row outside it, and there is nothing to be wrong
               about. */
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assistance_distributions')
                    ->whereColumn('assistance_distributions.assistance_allocation_id',
                                  'assistance_allocation_beneficiaries.assistance_allocation_id')
                    ->whereColumn('assistance_distributions.farmer_id',
                                  'assistance_allocation_beneficiaries.farmer_id');
            })
            ->count()
        : 0;

    /* Membership Applications is the second badged entry, and it earns a
       badge by the same Sept 2026 rule: these are applications sitting on
       this association's own desk, which is work the officer has to do, not
       a count of things they have been told about. The two stages counted
       are the two where the application is theirs to move; once forwarded
       it is the office's and the badge drops it. */
    $applicationsAwaitingUs = $associationId
        ? \App\Models\Farmer::query()
            ->notDeleted()
            ->where('association_id', $associationId)
            ->whereIn('membership_stage', [
                \App\Support\MembershipApplication::SUBMITTED,
                \App\Support\MembershipApplication::UNDER_REVIEW,
            ])
            ->whereHas('user', fn ($user) => $user->where('status', 'pending'))
            ->count()
        : 0;

    /* THE OFFICER'S MENU (revision sections 2 and 24)

       Planting Activities and Damage Reports used to be two entries here.
       They are now the two cards inside Crop Management Report, so this
       menu is eight short items rather than a list of tables.

       'pattern' may be a single route pattern or a list of them, so a
       parent item stays lit while you are on one of its children. */
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
            'label'       => 'Membership Applications',
            'filipino'    => 'Mga Aplikasyon',
            'route'       => 'association.membership-applications.index',
            'pattern'     => 'association.membership-applications.*',
            'icon'        => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM19 8v6M22 11h-6',
            'badge'       => $applicationsAwaitingUs,
            'badge_noun'  => 'application',
            'badge_label' => 'waiting on your review',
        ],
        [
            'label'    => 'Crop Management Report',
            'filipino' => 'Pamamahala ng Pananim',
            'route'    => 'association.crop-management.index',
            'pattern'  => [
                'association.crop-management.*',
                'association.planting.*',
                'association.reports.*',
            ],
            'icon'     => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
        ],
        [
            'label'       => 'Assistance Allocation & Tracking',
            'filipino'    => 'Tulong',
            'route'       => 'association.assistance.index',
            'pattern'     => 'association.assistance.*',
            'icon'        => 'M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21',
            'badge'       => $assistanceNeedingAction,
            'badge_noun'  => 'member',
            'badge_label' => 'still to be told to collect',
        ],
        [
            'label'    => 'Map & Visualization',
            'filipino' => 'Mapa',
            'route'    => 'association.map.index',
            'pattern'  => 'association.map.*',
            'icon'     => 'M9 20l-5.4 1.8A1 1 0 013 20.9V6.4a1 1 0 01.7-1L9 3.7m0 16.3l6-2.1m-6 2.1V3.7m6 14.2l5.4 1.8a1 1 0 001.3-1V4.2a1 1 0 00-.7-1L15 1.7m0 16.2V1.7m0 0L9 3.7',
        ],
        /* Where an officer WRITES announcements for their own members - a
           meeting, a reminder - which only that association's farmers
           receive. The bell stays the only door to the other direction,
           what has been sent TO this officer. */
        [
            'label'    => 'Announcements',
            'filipino' => 'Mga Anunsyo',
            'route'    => 'association.announcements.index',
            'pattern'  => 'association.announcements.*',
            'icon'     => 'M3 11a1 1 0 011-1h2.5L15 5.5v13L6.5 14H4a1 1 0 01-1-1v-2zM7.5 14.2V19a1 1 0 001 1h1.5a1 1 0 001-1v-3.3M18.5 9a3.5 3.5 0 010 6',
        ],
        [
            'label'    => 'Reports & Analytics',
            'filipino' => 'Mga Ulat',
            'route'    => 'association.summaries.index',
            'pattern'  => 'association.summaries.*',
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
    @php $classes = $item['route'] ? (request()->routeIs(...(array) $item['pattern']) ? $current : $idle) : $disabled; @endphp

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

{{-- Pinned to the very bottom of the sidebar: the nav element is a flex
     column (layouts/app.blade.php) and mt-auto here pushes this whole
     block - divider included - all the way down, not just after the
     last menu entry. --}}
<div class="mt-auto">
    <div class="my-2 border-t border-sidebar-border"></div>

    <a href="{{ route('help') }}" class="{{ request()->routeIs('help') ? $current : $idle }} justify-between">
        <span class="flex min-w-0 items-center gap-3">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 22a10 10 0 100-20 10 10 0 000 20zM9.5 9.5a2.5 2.5 0 113.2 2.4c-.5.2-.7.6-.7 1.1v.5m0 3h.01"/>
            </svg>

            <span class="min-w-0 flex-1 leading-tight">
                <span class="block truncate">Need Help</span>
                <span class="block truncate text-xs opacity-70">Kailangan ng Tulong?</span>
            </span>
        </span>
    </a>
</div>
