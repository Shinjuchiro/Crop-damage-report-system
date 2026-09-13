@extends('layouts.app')

@section('title', 'Planting Record')
@section('heading', 'Planting Record CP-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
@section('subheading', 'Submitted ' . $record->date_submitted?->format('F d, Y'))

@section('header-actions')
    <x-ui.button variant="outline" :href="route('farmer.planting.index')">Back to records</x-ui.button>
@endsection

@section('content')
<div class="space-y-5">

    <x-ui.card title="Crops in this record" :padded="false">
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th>Crop</th>
                    <th>Date Planted</th>
                    <th>Area</th>
                </tr>
            </x-slot:head>

            @foreach ($record->crops as $crop)
                <tr>
                    <td class="font-medium">
                        {{ $crop->crop?->name }}
                        @if ($crop->crop_specify)
                            <span class="block text-xs text-muted-foreground">{{ $crop->crop_specify }}</span>
                        @endif
                    </td>
                    <td class="text-muted-foreground">{{ $crop->date_planted?->format('M d, Y') }}</td>
                    <td>{{ number_format($crop->area_hectares, 2) }} ha</td>
                </tr>
            @endforeach

            <x-slot:foot>
                <tr>
                    <td colspan="2" class="font-semibold">Total area planted</td>
                    <td class="font-semibold">
                        {{ number_format($record->crops->sum('area_hectares'), 2) }} ha
                    </td>
                </tr>
            </x-slot:foot>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Farmer">
        <dl class="flex flex-wrap gap-x-10 gap-y-4">
            @php
                $details = [
                    'Name'         => $farmer->full_name,
                    'Association'  => $farmer->association?->name ?? 'Not set',
                    'Barangay'     => $farmer->barangay?->name ?? 'Not set',
                    'Farm size'    => $farmer->farm_size_hectares
                                        ? number_format($farmer->farm_size_hectares, 2) . ' ha'
                                        : 'Not set',
                ];
            @endphp

            @foreach ($details as $label => $value)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-card-foreground">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>

    <p class="text-sm text-muted-foreground">
        A submitted planting record cannot be edited here. If something is wrong, contact the
        Municipal Agriculture Office so the correction is recorded properly.
        <span class="mt-1 block">
            Kung may mali po, makipag-ugnayan sa tanggapan upang maitama ito nang maayos.
        </span>
    </p>
</div>
@endsection
