@extends('layouts.app')

@section('title', 'Members')
@section('heading', 'Members')
@section('heading-fil', 'Mga Kasapi')
@section('subheading', 'The farmers registered under ' . $association->name . '.')

@section('content')

{{--
    Proposal section 61. Read only: the association monitors its members, it
    does not edit their records. A farmer changes their own details, and the
    MAO approves registrations.

    Below sm the table becomes cards, because an officer checking a member on
    a phone should not have to scroll sideways.

    Selecting a member opens the record in a modal, the same arrangement the MAO
    screens use. Picking a member adds ?selected=<id> rather than navigating
    away, so the list, the filters and the page number all stay put. On a
    phone the panel takes over the screen and carries its own "Back to list"
    link, so nothing is lost on a small display.
--}}

<div class="space-y-4">

    {{-- The record opens in a modal now (see x-ui.detail-panel), so the
         list keeps the full width and stays put underneath it. This was
         a two-column grid with a 380px detail column pinned on the
         right. --}}
    <div>

    {{-- LIST --}}
    <div>

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['q', 'status', 'barangay', 'affected']">
                <x-ui.input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                            placeholder="First or last name" class="sm:w-52" />

                <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                             :options="['active' => 'Active', 'inactive' => 'Inactive']"
                             :selected="$filters['status'] ?? null" class="sm:w-40" />

                <x-ui.select name="barangay" placeholder="All barangays" onchange="this.form.submit()"
                             :options="$barangays->pluck('name', 'id')"
                             :selected="$filters['barangay'] ?? null" class="sm:w-44" />
            </x-ui.filter-bar>

            {{-- A one-tap shortcut for the question officers actually ask most --}}
            <div class="mb-4 flex flex-wrap gap-2 border-t border-border pt-3 sm:mb-5">
                <x-ui.button size="sm" :variant="($filters['affected'] ?? '') === 'yes' ? 'default' : 'outline'"
                             :href="route('association.members.index', ['affected' => 'yes'])">
                    Only members with a damage report
                </x-ui.button>
            </div>
        </div>

        @if ($members->isEmpty())
            <x-ui.empty title="No members match this"
                        icon="M16 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M9 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM22 19v-1.5a4 4 0 00-3-3.9"
                        message="Either nobody has registered under your association yet, or the filters above are hiding everyone. Farmers appear here once the Municipal Agriculture Office approves their registration.">
                <x-ui.button variant="outline" :href="route('association.members.index')">Clear filters</x-ui.button>
            </x-ui.empty>
        @else

        {{-- ONE PIECE OF STATE, BOTH LAYOUTS (revision section 8)

             `open` is an object keyed by member id rather than a single id,
             so an officer can leave two members open side by side and
             compare them. Alpine 3 proxies the object, so assigning a key
             is reactive without any further ceremony.

             It sits out here, around both the phone list and the table,
             because the two are the same list at different widths and a
             member expanded on a phone should still be expanded if the
             window is dragged wider. --}}
        <div x-data="{ open: {} }">

            {{-- ---------- PHONE ---------- --}}
            <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                @foreach ($members as $member)
                    <li class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">

                        {{-- The whole header is the toggle, so a thumb has
                             the full width of the card to aim at rather
                             than a chevron. --}}
                        <button type="button" class="block w-full p-3.5 text-left transition active:bg-muted"
                                @click="open[{{ $member->id }}] = ! open[{{ $member->id }}]"
                                :aria-expanded="open[{{ $member->id }}] ? 'true' : 'false'">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $member->full_name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ $member->barangay?->name ?? 'Barangay not set' }}
                                    </p>
                                </div>

                                <span class="flex shrink-0 items-center gap-2">
                                    <x-ui.status :value="$member->activity_status" />
                                    <svg class="h-4 w-4 text-muted-foreground transition-transform"
                                         :class="open[{{ $member->id }}] ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>
                                </span>
                            </div>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $member->planting_records_count }} planting
                                &middot; {{ $member->damage_reports_count }} damage
                                {{ $member->damage_reports_count === 1 ? 'report' : 'reports' }}
                            </p>
                        </button>

                        {{-- x-transition, not x-collapse: resources/js/app.js
                             imports core Alpine only, and the collapse plugin
                             is not among it. A directive Alpine does not know
                             is simply ignored, so the card would snap open
                             with no warning that the animation never ran. --}}
                        <div x-show="open[{{ $member->id }}]" x-cloak x-transition.opacity.duration.200ms>
                            @include('association.members._member-summary', ['member' => $member])
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden lg:block">
                <x-ui.table>
                    <x-slot:head>
                        <tr>
                            <th>Member</th>
                            <th>Barangay</th>
                            <th>Planting</th>
                            <th>Damage Reports</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($members as $member)
                        <tr>
                            <td>
                                {{-- The toggle is in this cell rather than a
                                     column of its own: a seventh column
                                     holding nothing but a chevron needs a
                                     heading that says nothing. --}}
                                <button type="button" class="flex items-start gap-2 text-left"
                                        @click="open[{{ $member->id }}] = ! open[{{ $member->id }}]"
                                        :aria-expanded="open[{{ $member->id }}] ? 'true' : 'false'"
                                        aria-label="Show {{ $member->full_name }} at a glance">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                                         :class="open[{{ $member->id }}] ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 9l6 6 6-6"/>
                                    </svg>

                                    {{-- THE MEMBER'S REFERENCE, NOT THEIR PHONE NUMBER
                                         (Oct 2026)

                                         This line printed
                                         $member->user->phone_number, so every
                                         member's mobile number was on the default
                                         Members list, before anyone expanded a row
                                         or clicked View. Revision section 3.1 names
                                         "personal contact information" among the
                                         things not to put in a summary table, and an
                                         association monitoring planting and damage
                                         counts is not phoning anybody from this
                                         screen.

                                         It is the same fix mao/farmers/index already
                                         made when it took the username out from under
                                         the name; this list was missed. The contact
                                         number is still one click away in the
                                         expanded summary and in the full profile,
                                         which is where section 3.2 puts it.

                                         Note the phone-card layout above never showed
                                         the number: it shows the planting and damage
                                         counts. So the two layouts disagreed about
                                         the same member, and this is also what brings
                                         them back into line. FARM- matches the
                                         identifier format on the MAO Farmers list, and
                                         gives this table the ID column that section
                                         3.1 asks for without adding a column. --}}
                                    <span class="min-w-0">
                                        <span class="font-medium">{{ $member->full_name }}</span>
                                        <span class="block text-xs text-muted-foreground">
                                            FARM-{{ str_pad($member->id, 3, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </span>
                                </button>
                            </td>

                            <td class="text-muted-foreground">{{ $member->barangay?->name ?? '-' }}</td>

                            <td class="text-muted-foreground">{{ $member->planting_records_count }}</td>

                            <td>
                                @if ($member->damage_reports_count > 0)
                                    <x-ui.badge variant="warning">{{ $member->damage_reports_count }}</x-ui.badge>
                                @else
                                    <span class="text-muted-foreground">0</span>
                                @endif
                            </td>

                            <td>
                                <x-ui.status :value="$member->activity_status" />
                                @if ($member->activity_status === 'inactive')
                                    <span class="mt-1 block text-xs text-muted-foreground">
                                        {{ $member->months_inactive }} months
                                    </span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="outline"
                                             :href="request()->fullUrlWithQuery(['selected' => $member->id])">
                                    View
                                </x-ui.button>
                            </td>
                        </tr>

                        {{-- The expansion is its own row, because a table
                             cannot hold anything between two rows. The
                             colspan has to match the six columns above it:
                             a wrong number here shows up at once as a
                             ragged row, which is better than a silent
                             misalignment.

                             x-collapse is NOT used here. It animates height
                             on the element it is placed on, and a <tr> does
                             not take a height the way a block does - the
                             row would jump instead of opening. The div
                             inside carries the padding, so the row has no
                             height of its own when it is closed. --}}
                        <tr x-show="open[{{ $member->id }}]" x-cloak class="!border-t-0">
                            <td colspan="6" class="!p-0">
                                @include('association.members._member-summary', ['member' => $member])
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        </div>
        @endif

        @if ($members->hasPages())
            <x-slot:footer>{{ $members->links() }}</x-slot:footer>
        @endif
    </x-ui.card>

    </div>{{-- /LIST --}}

    {{-- DETAIL --}}
    <x-ui.detail-panel :selected="$selected" :back-url="$backUrl" class="mt-4 lg:mt-0"
                        empty-text="Select a member from the list to view their details.">
        @if ($selected)
            @include('association.members._member-detail', [
                'member'      => $selected,
                'association' => $association,
                'inPanel'     => true,
            ])
        @endif
    </x-ui.detail-panel>

    </div>{{-- /lg:grid --}}
</div>
@endsection
