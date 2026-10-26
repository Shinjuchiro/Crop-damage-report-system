{{--
    ONE MEMBER, IN A MODAL (Oct 2026)

        <x-member.panel :farmer="$farmer" />
        <x-member.panel :farmer="$farmer" :account="true">
            ...optional actions, drawn under the status chips...
        </x-member.panel>

    Everything the old full profile page held (details, ownership, supporting
    documents, planting, damage, validation, assistance and history) as one
    flat column of sections, in the same shape as every other record modal:
    ID, name and status chips on top, then small uppercase headings with a
    muted label on the left and a bold value on the right.

    Shared by the MAO Farmers Directory and the association Members list so a
    farmer reads the same way for both. Read only.

    Expects the relations the two controllers' detailRelations() load.
    :account   adds an Account chip next to the Activity chip (MAO only).
    :contact   false hides the farmer's phone and email.
--}}
@props(['farmer', 'account' => false, 'contact' => true])

@php
    $row = fn ($label, $value) => '<div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">'
        . e($label) . '</dt><dd class="min-w-0 break-words text-right font-medium text-foreground">' . e($value !== null && $value !== '' ? $value : '-') . '</dd></div>';

    $tenant        = $farmer->ownership_type === 'tenant';
    $accountStatus = $farmer->user?->status ?? 'pending';
    $mainCrops     = $farmer->mainCrops->map(function ($m) {
        return $m->crop_specify ? ($m->crop?->name . ' (' . $m->crop_specify . ')') : $m->crop?->name;
    })->filter()->implode(', ');

    $planting = $farmer->plantingRecords->sortByDesc('date_submitted');
    $damage   = $farmer->damageReports->sortByDesc('created_at');
    $given    = $farmer->assistanceDistributions->sortByDesc('distributed_at');

    $head = 'mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground';
@endphp

<div>
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        FARM-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
    </p>
    <h3 class="mt-0.5 text-lg font-bold text-foreground">{{ $farmer->full_name }}</h3>
    <p class="text-sm text-muted-foreground">
        {{ $farmer->barangay?->name ?? 'No barangay' }} &middot; {{ $farmer->association?->name ?? 'No association' }}
    </p>
    <div class="mt-2 flex flex-wrap gap-2">
        @if ($account)
            <x-ui.status :value="$accountStatus" />
        @endif
        <x-ui.status :value="$farmer->activity_status" />
    </div>
    {{ $slot }}
</div>

<div class="mt-4 space-y-4 border-t border-border pt-4 text-sm">

    {{-- Activity --}}
    <div class="border-t border-border pt-4 first:border-t-0 first:pt-0">
        <p class="{{ $head }}">Activity</p>
        <dl class="space-y-1.5">
            {!! $row('Last Planting Report', $farmer->has_filed_planting_report ? $farmer->last_activity_date->format('M d, Y') : 'None yet') !!}
            {!! $row('Months Inactive', $farmer->activity_status === 'inactive' ? $farmer->months_inactive : 0) !!}
        </dl>
        <p class="mt-2 text-xs text-muted-foreground">
            A member goes inactive after {{ \App\Models\Farmer::INACTIVITY_MONTHS }} months with no crop planting activity.
            Logging in and browsing do not count.
        </p>
    </div>

    {{-- Personal --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Personal Information</p>
        <dl class="space-y-1.5">
            {!! $row('Date of Birth', $farmer->date_of_birth?->format('M d, Y')) !!}
            {!! $row('Sex', ucfirst((string) $farmer->sex)) !!}
            @if ($contact)
                {!! $row('Contact', $farmer->user?->phone_number) !!}
                {!! $row('Email', $farmer->user?->email) !!}
            @endif
            {!! $row('Address', $farmer->address) !!}
            {!! $row('Barangay', $farmer->barangay?->name) !!}
        </dl>
    </div>

    {{-- Ownership --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Farm Ownership</p>
        <dl class="space-y-1.5">
            {!! $row('Farmer Type', $tenant ? 'Tenant' : 'Land Owner') !!}
            @if ($tenant)
                {!! $row('Land Owner', $farmer->landowner_name) !!}
                {!! $row('Land Owner Contact', $farmer->landowner_contact) !!}
                {!! $row('Land Owner Location', $farmer->landowner_location) !!}
            @endif
        </dl>
        @if ($tenant)
            <x-farmer.supporting-documents :farmer="$farmer" class="mt-3" />
        @elseif ($farmer->barangay_certificate_path)
            <a href="{{ $farmer->barangay_certificate_url }}" target="_blank" rel="noopener"
               class="mt-3 inline-block text-xs font-semibold text-primary hover:underline">View Barangay Certificate</a>
        @endif
    </div>

    {{-- Farm --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Farm Information</p>
        <dl class="space-y-1.5">
            {!! $row('Farm Size', $farmer->farm_size_hectares ? number_format((float) $farmer->farm_size_hectares, 2) . ' ha' : null) !!}
            {!! $row('Main Crops', $mainCrops) !!}
        </dl>
    </div>

    {{-- Planting --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Crop Planting Records ({{ $planting->count() }})</p>
        @forelse ($planting as $record)
            <dl class="mb-2 space-y-1.5 border-b border-border/60 pb-2 last:mb-0 last:border-b-0 last:pb-0">
                {!! $row('Date Submitted', $record->date_submitted?->format('M d, Y')) !!}
                {!! $row('Crops', $record->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->join(', ')) !!}
                {!! $row('Total Area', number_format($record->crops->sum('area_hectares'), 2) . ' ha') !!}
            </dl>
        @empty
            <p class="text-muted-foreground">No planting activity recorded.</p>
        @endforelse
    </div>

    {{-- Damage --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Crop Damage Reports ({{ $damage->count() }})</p>
        @forelse ($damage as $report)
            <dl class="mb-2 space-y-1.5 border-b border-border/60 pb-2 last:mb-0 last:border-b-0 last:pb-0">
                <div class="flex items-center justify-between gap-3">
                    <dt class="shrink-0 font-medium text-foreground">{{ $report->full_reference }}</dt>
                    <dd><x-ui.status :value="$report->status" /></dd>
                </div>
                {!! $row('Filed', $report->created_at?->format('M d, Y')) !!}
                {!! $row('Cause', $report->damage_cause_label) !!}
                {!! $row('Crops', $report->crops->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)->filter()->join(', ')) !!}
                {!! $row('Damaged Area', number_format($report->crops->sum('damaged_area_hectares'), 2) . ' ha') !!}
            </dl>
        @empty
            <p class="text-muted-foreground">No damage reports filed.</p>
        @endforelse
    </div>

    {{-- Validation: both numbers, always (revision section 45) --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Validation &amp; Assessment</p>
        @forelse ($damage as $report)
            @php $check = $report->validation; @endphp
            <dl class="mb-2 space-y-1.5 border-b border-border/60 pb-2 last:mb-0 last:border-b-0 last:pb-0">
                {!! $row('Report', $report->full_reference) !!}
                {!! $row('Farmer Estimate', round($report->crops->avg('estimated_damage_percent')) . '%') !!}
                {!! $row('Technician Assessed', $check?->assessed_damage_percent !== null ? round($check->assessed_damage_percent) . '%' : 'Not yet inspected') !!}
                {!! $row('Severity', $check?->severity ? \App\Models\Validation::severityLabel($check->severity) : 'Not assessed') !!}
                {!! $row('Technician', $check?->technician?->display_name ?? $check?->technician?->username) !!}
                {!! $row('Inspected On', $check?->validated_at?->format('M d, Y')) !!}
            </dl>
        @empty
            <p class="text-muted-foreground">Nothing to inspect yet.</p>
        @endforelse
    </div>

    {{-- Assistance --}}
    <div class="border-t border-border pt-4">
        <p class="{{ $head }}">Assistance Received ({{ $given->count() }})</p>
        @forelse ($given as $d)
            <dl class="mb-2 space-y-1.5 border-b border-border/60 pb-2 last:mb-0 last:border-b-0 last:pb-0">
                {!! $row('Allocation', 'AA-' . str_pad((string) $d->assistance_allocation_id, 3, '0', STR_PAD_LEFT) . ($d->allocation?->display_name ? ' ' . $d->allocation->display_name : '')) !!}
                {!! $row('Date Given', $d->distributed_at?->format('M d, Y') ?? 'Not yet handed over') !!}
                {!! $row('Receipt', ucwords(str_replace('_', ' ', (string) $d->receipt_status))) !!}
            </dl>
        @empty
            <p class="text-muted-foreground">Nothing distributed yet.</p>
        @endforelse
    </div>

    {{-- History --}}
    <div class="border-t border-border pt-4">
        <x-member.history :farmer="$farmer" :flat="true" />
    </div>
</div>
