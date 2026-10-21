@extends('layouts.app')

@section('title', 'Assistance')
@section('heading', 'Assistance')
@section('heading-fil', 'Tulong')
@section('subheading', 'What the Municipal Agriculture Office has allocated to ' . $association->name . '.')

@section('content')

{{--
    Proposal section 61: the association monitors.

    READ-ONLY SINCE OCT 2026 (client decision)

    This page used to be the middle of the chain MAO -> Association ->
    Farmer, and it carried a Distribute button on every open row. The
    Municipal Agriculture Office hands assistance to the farmer itself now
    and records it, so the buttons are gone and this is a monitoring screen:
    what the office set aside for this association, and how far through it
    they are.

    The Remaining column went with the figures. There is no quantity on an
    allocation any more - how much a named farmer received is a financial
    detail about an identifiable person, and the client asked for it to stay
    out of the system. "Members given" replaces it: a count of people, which
    is what an officer actually wants to know and carries no figure.
--}}

<div class="space-y-4">

    <x-ui.alert variant="info">
        The Municipal Agriculture Office designates which of your members are assisted and records
        handing it to them. This page is for monitoring: open an allocation to see who was designated
        and who has already received theirs.
        <span class="mt-1 block">
            Ang tanggapan na po ang nagtatala ng pamamahagi. Ito po ay para sa pagsubaybay lamang.
        </span>
    </x-ui.alert>

    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['status']">
                <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                             :options="['allocated' => 'Allocated', 'distributed' => 'Partly distributed',
                                        'completed' => 'Completed', 'cancelled' => 'Cancelled']"
                             :selected="$filters['status'] ?? null" class="sm:w-48" />
            </x-ui.filter-bar>
        </div>

        @if ($allocations->isEmpty())
            <x-ui.empty title="Nothing allocated to you yet"
                        icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21"
                        message="When the Municipal Agriculture Office allocates assistance to your association's members, it appears here for you to follow.">
                <span class="text-xs text-muted-foreground">Wala pa pong naitalagang tulong sa inyong asosasyon.</span>
            </x-ui.empty>
        @else

            {{-- ---------- PHONE ---------- --}}
            <ul class="space-y-3 p-4 sm:grid sm:grid-cols-2 sm:gap-3 sm:space-y-0 lg:hidden">
                @foreach ($allocations as $allocation)

                    <li class="rounded-xl border border-border bg-card p-3.5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ $allocation->display_name }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}
                                </p>
                            </div>
                            <x-ui.status :value="$allocation->status" />
                        </div>

                        <div class="mt-3">
                            <x-ui.button size="sm" variant="outline" class="w-full"
                                         :href="route('association.assistance.show', $allocation)">
                                View
                            </x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- ---------- TABLET AND UP ---------- --}}
            <div class="hidden lg:block">
                <x-ui.table>
                    <x-slot:head>
                        {{-- FOUR COLUMNS, PLUS THE ONE THIS SCREEN EXISTS FOR
                             (revision section 3.1)

                             Remaining used to be the extra column, because
                             how much was left to hand out was why an officer
                             opened this page. There is no quantity to be
                             left of any more, so the column that earns its
                             place is "Members given": the same question
                             asked about people instead of goods, and it
                             carries no figure about anyone.

                             The disaster and the date allocated are in View.

                             "Allocation Status" rather than "Status": this
                             tracks the batch the office sent, not a member's
                             own case. A member's case carries one of the
                             four statuses in section 11.1 and is shown on
                             their own records. --}}
                        <tr>
                            <th>Allocation ID</th>
                            <th>Assistance</th>
                            <th>Members given</th>
                            <th>Allocation Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </x-slot:head>

                    @foreach ($allocations as $allocation)
                        <tr>
                            <td class="whitespace-nowrap font-medium">
                                AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}
                            </td>

                            <td>
                                <span class="font-medium">
                                    {{ $allocation->display_name }}
                                </span>
                            </td>

                            {{-- Counted from the rows, through the relations
                                 the controller eager-loads. Designated is
                                 how many members the office picked; given is
                                 how many of them it has handed to. --}}
                            <td class="whitespace-nowrap">
                                <span class="font-semibold">{{ $allocation->distributions_count ?? 0 }}</span>
                                <span class="text-xs text-muted-foreground">
                                    of {{ $allocation->beneficiaries_count ?? 0 }} designated
                                </span>
                            </td>

                            <td><x-ui.status :value="$allocation->status" /></td>

                            <td class="whitespace-nowrap text-right">
                                <x-ui.button size="sm" variant="outline"
                                             :href="route('association.assistance.show', $allocation)">
                                    View
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>

            @if ($allocations->hasPages())
                <x-slot:footer>{{ $allocations->links() }}</x-slot:footer>
            @endif
        @endif
    </x-ui.card>
</div>
@endsection
