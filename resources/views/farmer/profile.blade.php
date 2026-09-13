@extends('layouts.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('heading-fil', 'Aking Profile')
@section('subheading', 'View your personal information / Tingnan ang iyong personal na impormasyon')

@section('content')

{{--
    Laid out to match the web mockup.

    No max-w wrapper on this page. It used to have max-w-4xl, which stopped
    the cards at about 900px and left a large empty band on the right of a
    normal monitor. A read only page like this has no reason to be narrow:
    the field grid just uses more columns as the screen gets wider.

    Forms are the exception and still keep a width cap, because an input
    stretched across a 1600px monitor is horrible to fill in.
--}}

<div class="space-y-4">

    {{-- ================================================================
         HEADER: who this is, and their standing at a glance
    ================================================================= --}}
    <x-ui.card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-profile-photo :user="$farmer->user"
                :update-route="route('farmer.profile.photo.update')"
                :remove-route="route('farmer.profile.photo.remove')" />

            <div class="min-w-0 flex-1">
                <p class="text-xl font-bold text-card-foreground">{{ $farmer->full_name }}</p>
                <p class="text-sm font-medium text-primary">
                    {{ ucfirst($farmer->activity_status) }} Farmer
                </p>
            </div>
        </div>

        {{-- The five figures from the mockup. Every one is read from the
             database, none of them is stored as a display value. --}}
        <dl class="mt-5 grid gap-4 border-t border-border pt-5 sm:grid-cols-3 xl:grid-cols-5">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Last Activity</dt>
                <dd class="mt-1 text-sm font-medium">
                    {{ $farmer->last_activity_date?->format('F d, Y') ?? 'None recorded' }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Member Since</dt>
                <dd class="mt-1 text-sm font-medium">{{ $farmer->created_at?->format('F Y') }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Status / Katayuan
                </dt>
                <dd class="mt-1">
                    <x-ui.status :value="$farmer->user->status === 'active' ? 'verified' : $farmer->user->status" />
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Account Status</dt>
                <dd class="mt-1"><x-ui.status :value="$farmer->activity_status" /></dd>
            </div>
        </dl>
    </x-ui.card>

    {{-- ================================================================
         PERSONAL INFORMATION
         Four columns on a wide screen, two on a tablet, one on a phone.
         This is what actually fills the space the mockup fills.
    ================================================================= --}}
    @php
        $sexLabels = ['male' => 'Lalaki / Male', 'female' => 'Babae / Female'];

        $personal = [
            'First Name'          => $farmer->first_name,
            'Middle Name'         => $farmer->middle_name ?: 'N/A',
            'Last Name'           => $farmer->last_name,
            'Email Address'       => $farmer->user->email,
            'Date of Birth'       => $farmer->date_of_birth?->format('F d, Y') ?? 'Not provided',
            'Sex'                 => $sexLabels[$farmer->sex] ?? ucfirst((string) $farmer->sex),
            'Contact Number'      => $farmer->user->phone_number ?: 'Not provided',
            'Barangay'            => $farmer->barangay?->name ?? 'Not set',
            'Address'             => $farmer->address ?: 'Not provided',
            'Municipality'        => 'Tanza',
            'Province'            => 'Cavite',
            "Farmers' Association" => $farmer->association?->name ?? 'Not set',
        ];
    @endphp

    <x-ui.card title="Personal Information / Personal na Impormasyon">

        <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($personal as $label => $value)
                <div class="min-w-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-card-foreground">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>

    {{-- ================================================================
         FARM OWNERSHIP AND FARM INFORMATION, side by side
    ================================================================= --}}
    <div class="grid gap-4 lg:grid-cols-2">

        <x-ui.card title="Farm Ownership / Pagmamay-ari ng Sakahan">
            @php
                // Tenant fields only exist for tenants, so they are dropped
                // entirely rather than shown as empty rows (section 15).
                $ownership = array_filter([
                    'Ownership Type'      => $farmer->ownership_type === 'tenant' ? 'Tenant' : 'Land Owner',
                    'Land Owner Name'     => $farmer->ownership_type === 'tenant'
                                              ? ($farmer->landowner_name ?: 'Not provided') : null,
                    'Land Owner Contact'  => $farmer->ownership_type === 'tenant'
                                              ? ($farmer->landowner_contact ?: 'Not provided') : null,
                    'Land Owner Location' => $farmer->ownership_type === 'tenant'
                                              ? ($farmer->landowner_location ?: 'Not provided') : null,
                    'Barangay Certificate' => $farmer->ownership_type === 'tenant'
                                              ? null
                                              : ($farmer->barangay_certificate_path ? 'Submitted' : 'Not submitted'),
                ]);
            @endphp

            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($ownership as $label => $value)
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-card-foreground">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Farm Information / Impormasyon ng Sakahan">
            <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Farm Size</dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ $farmer->farm_size_hectares
                            ? number_format($farmer->farm_size_hectares, 2) . ' hectares'
                            : 'Not set' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Farm Location</dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ $farmer->barangay?->name ? $farmer->barangay->name . ', Tanza, Cavite' : 'Not set' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Planting Records</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $farmer->plantingRecords()->count() }}</dd>
                </div>
            </dl>

            {{-- The mockup shows farm coordinates here. We do not store a farm
                 centre point: coordinates belong to a damage report, captured
                 by the farmer and verified by the technician on site. Saying
                 so is better than showing an empty box. --}}
            <p class="mt-5 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
                Farm coordinates are recorded per damage report, not on the profile. The technician pins
                the verified location during the field inspection.
            </p>
        </x-ui.card>
    </div>

    {{-- ================================================================
         MAIN CROPS
    ================================================================= --}}
    <x-ui.card title="Main Crops / Pangunahing Pananim">
        @if ($farmer->mainCrops->isEmpty())
            <p class="text-sm text-muted-foreground">No main crops recorded.</p>
        @else
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Main Crops Grown
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach ($farmer->mainCrops as $main)
                    <x-ui.badge variant="primary">
                        {{ $main->crop?->name }}@if ($main->crop_specify) &middot; {{ $main->crop_specify }}@endif
                    </x-ui.badge>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.alert variant="info" title="Need a correction?">
        Registration details are reviewed and approved by the Municipal Agriculture Office, so they are
        not edited here. Contact the office and they will update your record.
        <span class="mt-1 block">
            Makipag-ugnayan po sa tanggapan ng MAO upang maitama ang inyong impormasyon.
        </span>
    </x-ui.alert>
</div>
@endsection
