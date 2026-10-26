{{--
    One member's record, in the two places an officer meets it.

    IN THE PANEL (the modal on the members list, $inPanel true)

    A summary and nothing more: who they are, whether they are active, and
    how many records they have. That is the "Andrea Santos / Tanza Farmers
    Association / 5 Reports / View Profile" card of revision section 6. The
    list answers "which member", the profile answers "what about them", and
    this modal is the handshake between the two. It used to carry every
    table in full, which turned a quick look at one row into a long scroll.

    ON THE PROFILE PAGE (association/members/show.blade.php, $inPanel false)

    The same six tabs the office's farmer profile has (section 9): Overview,
    Crop Planted, Crop Damage, Validation & Assessment, Assistance, History.
    The member's name is already the page heading, so nothing below repeats
    it against every record - which is the whole of section 8.

    Expects: $member (eager loaded, see MemberController::detailRelations())
             $association
             $inPanel  optional, default false
             $tab      optional, default 'overview'; ignored in the panel

    Entirely read only. Nothing here can be edited by an officer, and the
    password is never shown anywhere (section 20).
--}}
@php
    $inPanel = $inPanel ?? false;
    $tab     = $tab ?? 'overview';
@endphp

<div class="space-y-4">

@if ($inPanel)

    <x-ui.open-full-page class="!mt-0"
                         label="View full profile"
                         :href="route('association.members.show', $member)" />

        {{-- Status strip --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status :value="$member->activity_status" />

                <span class="text-xs text-muted-foreground">
                    @if ($member->has_filed_planting_report)
                        Last planting report {{ $member->last_activity_date->format('F d, Y') }}
                    @else
                        No planting report yet
                    @endif
                </span>

                @if ($member->activity_status === 'inactive')
                    <span class="text-xs text-muted-foreground">
                        &middot; {{ $member->months_inactive }} months inactive
                    </span>
                @endif
            </div>

            <p class="mt-2 text-xs text-muted-foreground">
                A member goes inactive after {{ \App\Models\Farmer::INACTIVITY_MONTHS }} months with no crop planting activity. Logging in and browsing
                do not count, and having no damage report does not make anybody inactive.
            </p>
        </x-ui.card>

        {{-- Personal and farm --}}
        <x-ui.card title="Member details">
            @php
                $details = [
                    'Contact'    => $member->user?->phone_number ?: 'Not provided',
                    'Email'      => $member->user?->email ?: 'Not provided',
                    'Barangay'   => $member->barangay?->name ?? 'Not set',
                    'Sex'        => ucfirst($member->sex ?? '-'),
                    'Ownership'  => $member->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                    'Farm Size'  => $member->farm_size_hectares
                                        ? number_format($member->farm_size_hectares, 2) . ' ha' : 'Not set',
                ];
            @endphp

            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                @foreach ($details as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-5 border-t border-border pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Address</p>
                <p class="mt-1 text-sm leading-relaxed">{{ $member->address }}</p>
            </div>

            @if ($member->ownership_type === 'tenant' && $member->landowner_name)
                <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Land owner</p>
                    <p class="mt-1 text-sm font-medium">{{ $member->landowner_name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ $member->landowner_contact ?: 'No contact' }}
                        @if ($member->landowner_location) &middot; {{ $member->landowner_location }} @endif
                    </p>
                </div>
            @endif

            <x-farmer.supporting-documents :farmer="$member" class="mt-4" />

            @if ($member->mainCrops->isNotEmpty())
                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Main crops</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($member->mainCrops as $main)
                            <x-ui.badge variant="primary">
                                {{ $main->crop_specify ?: $main->crop?->name }}
                            </x-ui.badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-ui.card>

    {{-- The counts the list row promised, and the reason to open the
         profile. Numbers only: the records themselves live behind the
         button above, not in a modal. --}}
    <x-ui.card title="Records">
        @php
            $tally = [
                'Crop planted' => $member->plantingRecords->count(),
                'Crop damage'  => $member->damageReports->count(),
                'Inspections'  => $member->damageReports->filter(fn ($report) => $report->validation?->validated_at)->count(),
                'Assistance'   => $member->assistanceDistributions->count(),
            ];
        @endphp

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($tally as $label => $total)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                    <dd class="mt-0.5 text-2xl font-bold tabular-nums text-card-foreground">{{ $total }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>

@else

    <x-member.tabs :farmer="$member" :current="$tab" />

    @if ($tab === 'overview')

            {{-- Status strip --}}
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.status :value="$member->activity_status" />

                    <span class="text-xs text-muted-foreground">
                        @if ($member->has_filed_planting_report)
                            Last planting report {{ $member->last_activity_date->format('F d, Y') }}
                        @else
                            No planting report yet
                        @endif
                    </span>

                    @if ($member->activity_status === 'inactive')
                        <span class="text-xs text-muted-foreground">
                            &middot; {{ $member->months_inactive }} months inactive
                        </span>
                    @endif
                </div>

                <p class="mt-2 text-xs text-muted-foreground">
                    A member goes inactive after {{ \App\Models\Farmer::INACTIVITY_MONTHS }} months with no crop planting activity. Logging in and browsing
                    do not count, and having no damage report does not make anybody inactive.
                </p>
            </x-ui.card>

            {{-- Personal and farm --}}
            <x-ui.card title="Member details">
                @php
                    $details = [
                        'Contact'    => $member->user?->phone_number ?: 'Not provided',
                        'Email'      => $member->user?->email ?: 'Not provided',
                        'Barangay'   => $member->barangay?->name ?? 'Not set',
                        'Sex'        => ucfirst($member->sex ?? '-'),
                        'Ownership'  => $member->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                        'Farm Size'  => $member->farm_size_hectares
                                            ? number_format($member->farm_size_hectares, 2) . ' ha' : 'Not set',
                    ];
                @endphp

                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ($details as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                            <dd class="mt-1 break-words text-sm font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-5 border-t border-border pt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Address</p>
                    <p class="mt-1 text-sm leading-relaxed">{{ $member->address }}</p>
                </div>

                @if ($member->ownership_type === 'tenant' && $member->landowner_name)
                    <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Land owner</p>
                        <p class="mt-1 text-sm font-medium">{{ $member->landowner_name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $member->landowner_contact ?: 'No contact' }}
                            @if ($member->landowner_location) &middot; {{ $member->landowner_location }} @endif
                        </p>
                    </div>
                @endif

                <x-farmer.supporting-documents :farmer="$member" class="mt-4" />

                @if ($member->mainCrops->isNotEmpty())
                    <div class="mt-4">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Main crops</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($member->mainCrops as $main)
                                <x-ui.badge variant="primary">
                                    {{ $main->crop_specify ?: $main->crop?->name }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-ui.card>

    @endif

    @if ($tab === 'planting')

            {{-- Planting --}}
            <x-ui.card title="Planting activities" :description="$member->plantingRecords->count() . ' recorded'"
                       :padded="false">
                @if ($member->plantingRecords->isEmpty())
                    <x-ui.empty title="No planting activity recorded"
                                message="This is what decides whether a member counts as active. Encourage them to record what they plant." />
                @else
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <th>Date Submitted</th>
                                <th>Crops</th>
                                <th>Total Area</th>
                            </tr>
                        </x-slot:head>

                        @foreach ($member->plantingRecords->sortByDesc('date_submitted') as $record)
                            <tr>
                                <td class="whitespace-nowrap">{{ $record->date_submitted?->format('M d, Y') }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($record->crops as $crop)
                                            <x-ui.badge variant="primary">
                                                {{ $crop->crop_specify ?: $crop->crop?->name }}
                                            </x-ui.badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

    @endif

    @if ($tab === 'damage')

            {{-- Damage reports --}}
            <x-ui.card title="Damage reports" :description="$member->damageReports->count() . ' filed'" :padded="false">
                @if ($member->damageReports->isEmpty())
                    <x-ui.empty title="No damage reports"
                                icon="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"
                                message="Nothing to report is good news. This member has not lost a crop." />
                @else
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <th>Report</th>
                                <th>Cause</th>
                                <th>Crops</th>
                                <th>Damaged Area</th>
                                <th>Farmer / Technician</th>
                                <th>Status</th>
                            </tr>
                        </x-slot:head>

                        @foreach ($member->damageReports->sortByDesc('created_at') as $report)
                            <tr>
                                <td class="whitespace-nowrap font-medium">
                                    {{ $report->full_reference }}
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $report->created_at?->format('M d, Y') }}
                                    </span>
                                </td>

                                <td>{{ $report->damage_cause_label }}</td>

                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($report->crops as $crop)
                                            <x-ui.badge variant="primary">
                                                {{ $crop->crop_specify ?: $crop->crop?->name }}
                                            </x-ui.badge>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="whitespace-nowrap">
                                    {{ number_format($report->crops->sum('damaged_area_hectares'), 2) }} ha
                                </td>

                                {{-- The farmer's estimate and the technician's figure,
                                     side by side. Section 45 keeps both forever. --}}
                                <td class="whitespace-nowrap">
                                    <span class="text-muted-foreground">
                                        {{ round($report->crops->avg('estimated_damage_percent')) }}%
                                    </span>
                                    <span class="mx-1 text-muted-foreground">/</span>
                                    <span class="font-semibold">
                                        {{ $report->validation?->assessed_damage_percent !== null
                                            ? round($report->validation->assessed_damage_percent) . '%'
                                            : 'not yet' }}
                                    </span>
                                </td>

                                <td><x-ui.status :value="$report->status" /></td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

    @endif

    @if ($tab === 'validation')

        {{-- Shared with the office's farmer profile: the same
             question asked from both sides, so it is one component. --}}
        <x-member.validation :farmer="$member" />

    @endif

    @if ($tab === 'assistance')

            {{-- Assistance --}}
            <x-ui.card title="Assistance received"
                       :description="$member->assistanceDistributions->count() . ' distributions recorded'"
                       :padded="false">
                @if ($member->assistanceDistributions->isEmpty())
                    <x-ui.empty title="No assistance recorded yet"
                                icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21"
                                message="Assistance you record giving to this member appears here.">
                        <x-ui.button variant="outline" :href="route('association.assistance.index')">
                            Open Assistance
                        </x-ui.button>
                    </x-ui.empty>
                @else
                    <x-ui.table>
                        <x-slot:head>
                            <tr>
                                <th>Allocation</th>
                                <th>For Report</th>
                                <th>Date Given</th>
                                <th>Member Confirmed?</th>
                            </tr>
                        </x-slot:head>

                        @foreach ($member->assistanceDistributions->sortByDesc('distributed_at') as $given)
                            <tr>
                                {{-- The Quantity column was here. No figure
                                     is recorded any more (Oct 2026, client
                                     decision), and the allocation reference
                                     is what identifies the batch. --}}
                                <td class="whitespace-nowrap font-medium">
                                    AA-{{ str_pad((string) $given->assistance_allocation_id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="whitespace-nowrap text-muted-foreground">
                                    {{ $given->damageReport?->full_reference ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap text-muted-foreground">
                                    {{ $given->distributed_at?->format('M d, Y') }}
                                </td>
                                <td>
                                    @if ($given->receipt_status === 'confirmed_received')
                                        <x-ui.badge variant="success" dot>Confirmed</x-ui.badge>
                                    @elseif ($given->receipt_status === 'not_received')
                                        <x-ui.badge variant="danger" dot>Says not received</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning" dot>Waiting</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

    @endif

    @if ($tab === 'history')

        <x-member.history :farmer="$member" />

    @endif

@endif

</div>
