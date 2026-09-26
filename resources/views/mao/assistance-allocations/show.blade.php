@extends('layouts.app')

@section('title', 'Allocation Details')
@section('heading', 'Allocation AA-' . str_pad($allocation->id, 3, '0', STR_PAD_LEFT))
@section('subheading', 'What the MAO sent to the association, and what the association handed to its members.')

@php
    $statusBadges = [
        'pending'     => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'allocated'   => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'distributed' => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'completed'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'cancelled'   => 'bg-secondary text-muted-foreground',
    ];

    $isCash = $allocation->is_cash;
@endphp

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Allocations
    </a>
@endsection

@section('content')
<div x-data="{ action: null }" class="space-y-6">

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Allocation --}}
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="mb-5 flex flex-wrap items-center gap-4">
            <span class="inline-flex rounded px-3 py-1.5 text-sm font-semibold {{ $statusBadges[$allocation->status] ?? $statusBadges['pending'] }}">
                {{ ucfirst($allocation->status) }}
            </span>
            <span class="inline-flex rounded px-3 py-1.5 text-sm font-medium {{ $isCash ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
                {{ $isCash ? 'Cash' : 'In-Kind' }}
            </span>
        </div>

        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-muted-foreground">Assistance</dt><dd class="font-medium text-foreground">{{ $allocation->display_name }}</dd></div>
            <div><dt class="text-muted-foreground">Association</dt><dd class="font-bold text-foreground">{{ $allocation->association?->name ?? '-' }}</dd></div>
            <div><dt class="text-muted-foreground">Disaster Event</dt><dd class="font-medium text-foreground">{{ $allocation->disaster?->name ?? 'Not tied to an event' }}</dd></div>
            <div><dt class="text-muted-foreground">Crop</dt><dd class="font-medium text-foreground">{{ $allocation->crop?->name ?? 'Not crop specific' }}</dd></div>
            <div>
                <dt class="text-muted-foreground">{{ $isCash ? 'Amount Allocated' : 'Quantity Allocated' }}</dt>
                <dd class="font-medium text-foreground">
                    {{ $allocation->allocated_quantity !== null ? number_format($allocation->allocated_quantity, 2) : 'Not specified' }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">{{ $isCash ? 'Amount Released' : 'Quantity Released' }}</dt>
                <dd class="font-medium text-foreground">
                    {{ $allocation->distributed_quantity !== null ? number_format($allocation->distributed_quantity, 2) : 'Not yet released' }}
                </dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-muted-foreground">Description</dt>
                <dd class="font-medium text-foreground">
                    {{ $allocation->in_kind_description ?: ($allocation->assistance?->description ?: 'Not specified') }}
                </dd>
            </div>
            <div><dt class="text-muted-foreground">Allocated On</dt><dd class="font-medium text-foreground">{{ $allocation->allocated_at?->format('M d, Y') }}</dd></div>
            <div>
                <dt class="text-muted-foreground">Allocated By</dt>
                <dd class="font-bold text-foreground">
                    {{ $allocation->allocatedBy?->full_name ?: $allocation->allocatedBy?->username ?? '-' }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Released To Association</dt>
                <dd class="font-medium text-foreground">
                    {{ $allocation->distributed_to_association_at?->format('M d, Y') ?? 'Not yet' }}
                </dd>
            </div>

            @if ($allocation->remarks)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-muted-foreground">Remarks</dt>
                    <dd class="whitespace-pre-line font-medium text-foreground">{{ $allocation->remarks }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- MAO-selected beneficiaries --}}
    @if ($allocation->beneficiaries->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Qualified Beneficiaries</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    The farmers MAO selected when this pool was allocated. Informational for the association's
                    reference - it does not by itself record that anyone was handed anything. The association
                    still distributes and records that separately, below.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-6 py-3 font-medium">Farmer</th>
                            <th class="px-6 py-3 font-medium">Barangay</th>
                            <th class="px-6 py-3 font-medium">Damage Report</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($allocation->beneficiaries as $beneficiary)
                            <tr class="hover:bg-muted/60">
                                <td class="px-6 py-3 font-bold text-foreground">{{ $beneficiary->farmer?->full_name ?? 'Unknown' }}</td>
                                <td class="px-6 py-3 text-muted-foreground">{{ $beneficiary->farmer?->barangay?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    @if ($beneficiary->damageReport)
                                        <a href="{{ route('mao.damage-reports.show', $beneficiary->damageReport) }}"
                                           class="text-sky-700 underline hover:text-sky-900">
                                            {{ $beneficiary->damageReport->reference }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Supporting documents --}}
    @if ($allocation->documents->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-6 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Supporting Documents</h3>
            </div>

            <ul class="divide-y divide-border">
                @foreach ($allocation->documents as $document)
                    <li class="flex items-center justify-between gap-4 px-6 py-3 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            <svg class="h-5 w-5 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z"/>
                            </svg>
                            <span class="truncate font-medium text-foreground">{{ $document->file_name }}</span>
                            @if ($document->size_label)
                                <span class="shrink-0 text-xs text-muted-foreground">({{ $document->size_label }})</span>
                            @endif
                        </span>
                        <a href="{{ $document->url }}" target="_blank" rel="noopener"
                           class="shrink-0 text-sm font-medium text-sky-700 underline hover:text-sky-900">
                            View
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Distributions to farmers --}}
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-6 py-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-green-800">Distribution to Farmers</h3>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Recorded by the association. The farmer confirms receipt separately, so distributed and
                received are tracked as two different facts.
            </p>
        </div>

        @if ($allocation->distributions->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-6 py-3 font-medium">Distribution ID</th>
                            <th class="px-6 py-3 font-medium">Farmer</th>
                            <th class="px-6 py-3 font-medium">Damage Report</th>
                            <th class="px-6 py-3 text-right font-medium">Quantity</th>
                            <th class="px-6 py-3 font-medium">Distributed</th>
                            <th class="px-6 py-3 text-center font-medium">Distribution</th>
                            <th class="px-6 py-3 text-center font-medium">Farmer Confirmation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($allocation->distributions as $distribution)
                            <tr class="hover:bg-muted/60">
                                <td class="px-6 py-3 text-foreground">
                                    DIST-{{ str_pad($distribution->id, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-3 font-bold text-foreground">
                                    {{ $distribution->farmer?->full_name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">
                                    @if ($distribution->damageReport)
                                        <a href="{{ route('mao.damage-reports.show', $distribution->damageReport) }}"
                                           class="text-sky-700 underline hover:text-sky-900">
                                            DR-{{ str_pad($distribution->damage_report_id, 4, '0', STR_PAD_LEFT) }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-foreground">
                                    {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{{ $distribution->distributed_at?->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex rounded bg-secondary px-3 py-1 text-xs font-medium text-foreground">
                                        {{ ucwords(str_replace('_', ' ', $distribution->distribution_status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @php $receipt = $distribution->receipt_status; @endphp
                                    <span class="inline-flex rounded px-3 py-1 text-xs font-medium
                                        {{ $receipt === 'confirmed_received'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                                            : ($receipt === 'not_received' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                                        {{ ucwords(str_replace('_', ' ', $receipt)) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-medium text-muted-foreground">Nothing distributed yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    The association records each farmer's share from its own dashboard.
                </p>
            </div>
        @endif
    </div>

    {{-- MAO actions --}}
    @if (! in_array($allocation->status, ['completed', 'cancelled'], true))
        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-1 text-sm font-semibold uppercase tracking-wide text-green-800">Update Allocation</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                Mark the allocation as released once the goods or funds physically reach the association,
                and completed once distribution is finished.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row">
                @if ($allocation->status !== 'distributed')
                    <button type="button" @click="action = 'distributed'"
                            class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                        Mark as Released
                    </button>
                @endif

                <button type="button" @click="action = 'completed'"
                        class="rounded-lg border border-green-300 px-5 py-2.5 text-sm font-semibold text-green-800 hover:bg-accent">
                    Mark as Completed
                </button>

                <button type="button" @click="action = 'cancelled'"
                        class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Cancel Allocation
                </button>
            </div>
        </div>

        {{-- Confirmation --}}
        <div x-show="action" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div x-show="action" x-transition class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-foreground">
                    Confirm: mark as <span x-text="action"></span>
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Allocation <strong>AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}</strong>
                    for <strong>{{ $allocation->association?->name }}</strong>.
                </p>

                <form method="POST" action="{{ route('mao.assistance-allocations.status', $allocation) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" :value="action">

                    <div x-show="action === 'distributed'" x-cloak class="mb-4">
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            {{ $isCash ? 'Amount released' : 'Quantity released' }}
                        </label>
                        <input type="number" step="0.01" min="0" name="distributed_quantity"
                               value="{{ $allocation->allocated_quantity }}"
                               class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            This can legitimately differ from what was allocated.
                        </p>
                    </div>

                    <label class="mb-1.5 block text-sm font-medium text-foreground">Remarks (optional)</label>
                    <textarea name="remarks" rows="3"
                              class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-ring"></textarea>

                    <div class="flex gap-3">
                        <button type="button" @click="action = null"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
