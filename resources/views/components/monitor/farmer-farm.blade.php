{{--
    Farmer, farm ownership and farm information, as three read-only blocks.
    Shared by the Crop Planted, Crop Damage and Validation modals so the three
    pages show the same farmer details the same way.

        <x-monitor.farmer-farm :farmer="$farmer" />

    Needs the farmer's user, association, barangay and mainCrops.crop loaded.
    Pass :contact="false" where the viewer has no business with the phone number
    (the association sees its members' details on the member's own page).
--}}
@props(['farmer', 'contact' => true])

@php
    $row = fn ($label, $value) => '<div class="flex justify-between gap-3"><dt class="shrink-0 text-muted-foreground">'
        . e($label) . '</dt><dd class="min-w-0 text-right font-medium text-foreground">' . e($value ?: '-') . '</dd></div>';
    $tenant = $farmer->ownership_type === 'tenant';
    $mainCrops = $farmer->mainCrops->map(fn ($m) => $m->crop?->name)->filter()->implode(', ');
@endphp

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-xl border border-border p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farmer Information</p>
        <dl class="space-y-1.5 text-sm">
            {!! $row('Name', $farmer->full_name) !!}
            {!! $row('Association', $farmer->association?->name) !!}
            {!! $row('Barangay', $farmer->barangay?->name) !!}
            @if ($contact)
                {!! $row('Contact', $farmer->user?->phone_number) !!}
            @endif
        </dl>
    </div>

    <div class="rounded-xl border border-border p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Ownership</p>
        <dl class="space-y-1.5 text-sm">
            {!! $row('Farmer Type', $tenant ? 'Tenant' : 'Land Owner') !!}
            @if ($tenant)
                {!! $row('Land Owner', $farmer->landowner_name) !!}
                {!! $row('Land Owner Contact', $farmer->landowner_contact) !!}
                {!! $row('Land Owner Location', $farmer->landowner_location) !!}
            @endif
        </dl>
    </div>

    <div class="rounded-xl border border-border p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Farm Information</p>
        <dl class="space-y-1.5 text-sm">
            {!! $row('Farm Size', $farmer->farm_size_hectares !== null ? number_format((float) $farmer->farm_size_hectares, 2) . ' ha' : null) !!}
            {!! $row('Farm Location', trim(($farmer->address ? $farmer->address . ', ' : '') . ($farmer->barangay?->name ?? ''), ', ')) !!}
            {!! $row('Main Crops', $mainCrops) !!}
        </dl>
    </div>
</div>
