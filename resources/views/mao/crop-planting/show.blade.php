@extends('layouts.app')

@section('title', 'Planting Record')
@section('heading', 'Crop Planting Record')
@section('subheading', 'Submitted by the farmer as proof of farming activity.')

@php $farmer = $plantingRecord->farmer; @endphp

@section('header-actions')
    <div class="flex items-center gap-2">
        <a href="{{ route('mao.crop-planting.index') }}"
           class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
            Back to Monitoring
        </a>
        <a href="{{ route('mao.crop-planting.edit', $plantingRecord) }}"
           class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
            Edit
        </a>

        @if ($plantingRecord->is_archived)
            <form method="POST" action="{{ route('mao.crop-planting.restore', $plantingRecord) }}"
                  data-confirm="Restore this planting record to the active monitoring list?"
                  data-confirm-title="Restore planting record"
                  data-confirm-action="Confirm Restore">
                @csrf @method('PUT')
                <button type="submit" class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
                    Restore
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('mao.crop-planting.archive', $plantingRecord) }}"
                  data-confirm="This record will be removed from active monitoring. It stays fully intact for audit purposes, does not change the farmer's Active/Inactive history, and can be restored at any time from the Archive page."
                  data-confirm-title="Archive this planting record?"
                  data-confirm-action="Confirm Archive">
                @csrf @method('PUT')
                <button type="submit" class="inline-block rounded-lg border border-amber-200 bg-card px-5 py-2.5 text-sm font-medium text-amber-700 hover:bg-amber-50">
                    Archive
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Farmer --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farmer</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-muted-foreground">Name</dt>
                <dd class="font-medium text-foreground">
                    <a href="{{ route('mao.farmers.show', $farmer) }}" class="text-green-800 hover:underline">
                        {{ $farmer->full_name }}
                    </a>
                </dd>
            </div>
            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $farmer->association?->name ?? '-' }}</dd></div>
            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $farmer->barangay?->name ?? '-' }}</dd></div>
            <div><dt class="text-muted-foreground">Date Submitted</dt><dd class="font-medium text-foreground">{{ $plantingRecord->date_submitted?->format('M d, Y') }}</dd></div>
        </dl>
    </div>

    {{-- Crops --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Crops Planted</h3>
            <p class="text-sm text-muted-foreground">
                Total area:
                <span class="font-semibold text-foreground">
                    {{ number_format($plantingRecord->crops->sum('area_hectares'), 2) }} ha
                </span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-6 py-3 font-medium">Crop</th>
                        <th class="px-6 py-3 font-medium">Date Planted</th>
                        <th class="px-6 py-3 text-right font-medium">Farm Area</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($plantingRecord->crops as $recordCrop)
                        <tr>
                            <td class="px-6 py-3 font-medium text-foreground">
                                {{ $recordCrop->crop?->name ?? '-' }}
                                @if ($recordCrop->crop_specify)
                                    <span class="block text-xs font-normal text-muted-foreground">{{ $recordCrop->crop_specify }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-muted-foreground">{{ $recordCrop->date_planted?->format('M d, Y') }}</td>
                            <td class="px-6 py-3 text-right tabular-nums text-foreground">{{ $recordCrop->area_hectares }} ha</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Photos --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Photos</h3>

        @if ($plantingRecord->photos->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($plantingRecord->photos as $photo)
                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank"
                       class="block overflow-hidden rounded-lg border border-border">
                        <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Planting photo"
                             class="h-36 w-full object-cover transition hover:scale-105">
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted-foreground">No photos were attached to this record.</p>
        @endif
    </div>
</div>
@endsection
