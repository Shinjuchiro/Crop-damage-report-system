{{--
    One member's full detail: profile, planting history, damage reports and
    assistance received.

    Shared by two places, which is the whole point of it being a partial:

      - association/members/index.blade.php renders it inside the right-hand
        x-ui.detail-panel when a row is selected with ?selected=<id>
      - association/members/show.blade.php renders it as a full page, so the
        older /members/{member} links, bookmarks and anything already pointing
        at them keep working exactly as before

    Expects: $member (eager loaded, see MemberController::detailRelations())
             $association

    Entirely read only. Nothing here can be edited by an officer, and the
    password is never shown anywhere (section 20).
--}}
<div class="space-y-4">

    {{-- Status strip --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.status :value="$member->activity_status" />

            <span class="text-xs text-muted-foreground">
                Last activity
                {{ $member->last_activity_date?->format('F d, Y') ?? 'not recorded' }}
            </span>

            @if ($member->activity_status === 'inactive')
                <span class="text-xs text-muted-foreground">
                    &middot; {{ $member->months_inactive }} months inactive
                </span>
            @endif
        </div>

        <p class="mt-2 text-xs text-muted-foreground">
            A member goes inactive after 3 months with no crop planting activity. Logging in and browsing
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
                            {{ $report->reference }}
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
                        <th>Assistance</th>
                        <th>Quantity</th>
                        <th>For Report</th>
                        <th>Date Given</th>
                        <th>Member Confirmed?</th>
                    </tr>
                </x-slot:head>

                @foreach ($member->assistanceDistributions->sortByDesc('distributed_at') as $given)
                    <tr>
                        <td class="font-medium">
                            {{ $given->allocation?->display_name
                                ?? $given->in_kind_description ?? 'Assistance' }}
                        </td>
                        <td class="whitespace-nowrap">{{ number_format((float) $given->quantity, 2) }}</td>
                        <td class="whitespace-nowrap text-muted-foreground">
                            {{ $given->damageReport?->reference ?? '-' }}
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
</div>
