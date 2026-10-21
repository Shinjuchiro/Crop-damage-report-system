{{--
    A MEMBER AT A GLANCE, INSIDE THEIR OWN ROW

        @include('association.members._member-summary', ['member' => $member])

    What opens when an officer expands a member on the Members list
    (revision section 8). One partial, included by both the phone card and
    the table row, so the two cannot drift into saying different things
    about the same member.

    WHAT IS IN IT, AND WHY ONLY THIS

    The question an officer is asking when they expand a row is "what has
    this member been doing, and do I need to do anything about them". So:
    how to reach them, what they farm, the last thing they planted, and the
    last damage they reported. Not their whole history - that is the
    profile, one click further on, and repeating it here would make the
    list longer than the page it is meant to save you leaving.

    Both "latest" records come from one-of-many relations eager-loaded by
    the controller, so expanding a row costs nothing: everything here is
    already in memory by the time the page renders.
--}}

<div class="space-y-4 border-t border-border bg-muted/40 px-4 py-4 sm:px-5">

    {{-- WHO THEY ARE --}}
    <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $facts = [
                'Contact'   => $member->user?->phone_number ?: 'No contact number',
                'Barangay'  => $member->barangay?->name ?? 'Not set',
                'Ownership' => $member->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                'Farm Size' => $member->farm_size_hectares
                    ? number_format($member->farm_size_hectares, 2) . ' ha'
                    : 'Not set',
            ];
        @endphp

        @foreach ($facts as $label => $value)
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</p>
                <p class="truncate text-sm font-semibold text-foreground">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- WHAT THEY FARM --}}
    <div>
        <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">Main Crops</p>

        @if ($member->mainCrops->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($member->mainCrops as $main)
                    <x-ui.badge variant="primary">
                        {{ $main->crop_specify ?: $main->crop?->name }}
                    </x-ui.badge>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted-foreground">None recorded</p>
        @endif
    </div>

    {{-- THE LAST OF EACH --}}
    <div class="grid gap-3 lg:grid-cols-2">

        {{-- LATEST PLANTING --}}
        <div class="rounded-xl border border-border bg-card p-3.5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Latest Planting</p>
                <span class="text-xs text-muted-foreground">
                    {{ $member->planting_records_count }}
                    {{ \Illuminate\Support\Str::plural('record', $member->planting_records_count) }} in all
                </span>
            </div>

            @if ($planting = $member->latestPlantingRecord)
                <p class="text-sm font-semibold text-card-foreground">
                    {{ $planting->date_submitted?->format('d M Y') ?? 'Date not set' }}
                </p>

                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @forelse ($planting->crops as $crop)
                        <x-ui.badge>
                            {{ $crop->crop_specify ?: $crop->crop?->name }}
                            &middot; {{ number_format($crop->area_hectares, 2) }} ha
                        </x-ui.badge>
                    @empty
                        <span class="text-sm text-muted-foreground">No crops on this record</span>
                    @endforelse
                </div>
            @else
                <p class="text-sm text-muted-foreground">
                    Nothing planted on record yet. A planting record is also what keeps a
                    member Active, so this is worth a nudge.
                </p>
            @endif
        </div>

        {{-- LATEST DAMAGE REPORT --}}
        <div class="rounded-xl border border-border bg-card p-3.5">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Latest Damage Report</p>
                <span class="text-xs text-muted-foreground">
                    {{ $member->damage_reports_count }}
                    {{ \Illuminate\Support\Str::plural('report', $member->damage_reports_count) }} in all
                </span>
            </div>

            @if ($report = $member->latestDamageReport)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-card-foreground">{{ $report->reference }}</span>
                    <x-ui.status :value="$report->status" />
                    <span class="text-xs text-muted-foreground">
                        {{ $report->created_at?->format('d M Y') }}
                    </span>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach ($report->crops as $crop)
                        <x-ui.badge variant="warning">
                            {{ $crop->crop_specify ?: $crop->crop?->name }}
                            &middot; {{ number_format($crop->damaged_area_hectares, 2) }} ha
                        </x-ui.badge>
                    @endforeach
                </div>

                {{-- The technician's own figure, when there is one. The
                     farmer's estimate is deliberately not shown beside it
                     here: this is a summary, and two percentages with no
                     room to explain which is which would be read as one
                     number contradicting itself. The full details page
                     shows both, properly labelled. --}}
                @if ($report->validation?->validated_at)
                    <p class="mt-2 text-xs text-muted-foreground">
                        Inspected {{ $report->validation->validated_at->format('d M Y') }}
                        @if ($report->validation->assessed_damage_percent !== null)
                            &middot; assessed at {{ (float) $report->validation->assessed_damage_percent }}%
                        @endif
                        @if ($report->validation->severity)
                            &middot; {{ \App\Models\Validation::severityLabel($report->validation->severity) }}
                        @endif
                    </p>
                @endif
            @else
                <p class="text-sm text-muted-foreground">
                    No damage reported. That is good news, not a gap: a member with no
                    damage is a member whose crops survived.
                </p>
            @endif
        </div>
    </div>

    {{-- Out of the row, into the whole record. --}}
    <div class="flex flex-wrap gap-2">
        <x-ui.button size="sm" :href="route('association.members.show', $member)">
            View full profile
        </x-ui.button>
    </div>
</div>
