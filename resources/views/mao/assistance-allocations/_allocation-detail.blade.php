{{-- Shared allocation detail content (Sept 2026): the exact same markup is
     used by the Assistance Allocation working screen and by Allocation
     History's side panel, so the two can never quietly drift apart - the
     same reasoning behind AssistanceAllocationController::detailRelations().
     Expects $allocation (an AssistanceAllocation with detailRelations()
     eager-loaded) and assumes an ancestor `x-data="{ action: null }"` for
     the status-update confirmation modal below. --}}
@php
    $statusBadges = [
        'pending'     => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'allocated'   => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
        'distributed' => 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300',
        'completed'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'cancelled'   => 'bg-secondary text-muted-foreground',
    ];
    $isCash = $allocation->assistance?->type === 'cash';
@endphp

<div class="mb-4">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        AA-{{ str_pad($allocation->id, 3, '0', STR_PAD_LEFT) }}
    </p>
    <h3 class="mt-0.5 text-lg font-semibold text-foreground">{{ $allocation->assistance?->name ?? 'Assistance' }}</h3>
    <p class="text-sm text-muted-foreground">{{ $allocation->association?->name ?? 'No association' }}</p>
    <div class="mt-2 flex flex-wrap gap-2">
        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $statusBadges[$allocation->status] ?? $statusBadges['pending'] }}">
            {{ ucfirst($allocation->status) }}
        </span>
        <span class="inline-flex rounded px-2.5 py-1 text-xs font-medium {{ $isCash ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' }}">
            {{ $isCash ? 'Cash' : 'In-Kind' }}
        </span>
    </div>
    <a href="{{ route('mao.assistance-allocations.show', $allocation) }}"
       class="mt-3 inline-block text-xs font-medium text-sky-700 underline hover:text-sky-900">
        Open full page
    </a>
</div>

<div class="space-y-4 border-t border-border pt-4 text-sm">
    {{-- Allocation details --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Allocation</p>
        <dl class="space-y-1.5">
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Disaster Event</dt><dd class="font-medium text-foreground">{{ $allocation->disaster?->name ?? 'Not tied to an event' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Crop</dt><dd class="font-medium text-foreground">{{ $allocation->crop?->name ?? 'Not crop specific' }}</dd></div>
            <div class="flex justify-between gap-3">
                <dt class="text-muted-foreground">{{ $isCash ? 'Allocated' : 'Qty Allocated' }}</dt>
                <dd class="font-medium text-foreground">{{ $allocation->allocated_quantity !== null ? number_format($allocation->allocated_quantity, 2) : 'Not specified' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-muted-foreground">{{ $isCash ? 'Released' : 'Qty Released' }}</dt>
                <dd class="font-medium text-foreground">{{ $allocation->distributed_quantity !== null ? number_format($allocation->distributed_quantity, 2) : 'Not yet released' }}</dd>
            </div>
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Allocated On</dt><dd class="font-medium text-foreground">{{ $allocation->allocated_at?->format('M d, Y') }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Allocated By</dt><dd class="font-bold text-foreground">{{ $allocation->allocatedBy?->full_name ?: $allocation->allocatedBy?->username ?? '-' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Released To Assoc.</dt><dd class="font-medium text-foreground">{{ $allocation->distributed_to_association_at?->format('M d, Y') ?? 'Not yet' }}</dd></div>
            <div>
                <dt class="text-muted-foreground">Description</dt>
                <dd class="font-medium text-foreground">{{ $allocation->in_kind_description ?: ($allocation->assistance?->description ?: 'Not specified') }}</dd>
            </div>
            @if ($allocation->remarks)
                <div>
                    <dt class="text-muted-foreground">Remarks</dt>
                    <dd class="whitespace-pre-line font-medium text-foreground">{{ $allocation->remarks }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Qualified beneficiaries --}}
    @if ($allocation->beneficiaries->isNotEmpty())
        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Qualified Beneficiaries</p>
            @foreach ($allocation->beneficiaries as $beneficiary)
                <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                    <p class="font-bold text-foreground">{{ $beneficiary->farmer?->full_name ?? 'Unknown' }}</p>
                    <p class="text-muted-foreground">
                        {{ $beneficiary->farmer?->barangay?->name ?? '-' }}
                        @if ($beneficiary->damageReport)
                            &middot;
                            <a href="{{ route('mao.damage-reports.show', $beneficiary->damageReport) }}"
                               class="text-sky-700 underline hover:text-sky-900">{{ $beneficiary->damageReport->reference }}</a>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Supporting documents --}}
    @if ($allocation->documents->isNotEmpty())
        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supporting Documents</p>
            @foreach ($allocation->documents as $document)
                <div class="mb-2 flex items-center justify-between gap-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                    <span class="min-w-0 truncate font-medium text-foreground">{{ $document->file_name }}</span>
                    <a href="{{ $document->url }}" target="_blank" rel="noopener"
                       class="shrink-0 font-medium text-sky-700 underline hover:text-sky-900">View</a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Distributions to farmers --}}
    <div class="border-t border-border pt-4">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Distribution to Farmers</p>
        <p class="mb-2 text-xs text-muted-foreground">Recorded by the association; the farmer confirms receipt separately.</p>
        @forelse ($allocation->distributions as $distribution)
            <div class="mb-2 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-bold text-foreground">{{ $distribution->farmer?->full_name ?? 'Unknown' }}</p>
                    @php $receipt = $distribution->receipt_status; @endphp
                    <span class="inline-flex rounded px-2 py-0.5 font-medium
                        {{ $receipt === 'confirmed_received'
                            ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'
                            : ($receipt === 'not_received' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                        {{ ucwords(str_replace('_', ' ', $receipt)) }}
                    </span>
                </div>
                <p class="text-muted-foreground">
                    Qty: {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                    &middot; {{ $distribution->distributed_at?->format('M d, Y') ?? 'Not yet' }}
                    &middot; {{ ucwords(str_replace('_', ' ', $distribution->distribution_status)) }}
                </p>
            </div>
        @empty
            <p class="text-xs text-muted-foreground">Nothing distributed yet - the association records this from its own dashboard.</p>
        @endforelse
    </div>

    {{-- MAO actions --}}
    @if (! in_array($allocation->status, ['completed', 'cancelled'], true))
        <div class="border-t border-border pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Update Allocation</p>
            <div class="flex flex-col gap-2">
                @if ($allocation->status !== 'distributed')
                    <button type="button" @click="action = 'distributed'"
                            class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:brightness-110">
                        Mark as Released
                    </button>
                @endif

                <button type="button" @click="action = 'completed'"
                        class="rounded-lg border border-green-300 px-4 py-2 text-xs font-semibold text-green-800 hover:bg-accent">
                    Mark as Completed
                </button>

                <button type="button" @click="action = 'cancelled'"
                        class="rounded-lg border border-red-300 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                    Cancel Allocation
                </button>
            </div>
        </div>

        {{-- Confirmation --}}
        <div x-show="action" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div x-show="action" x-transition class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
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
