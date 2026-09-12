@extends('layouts.app')

@section('title', 'Assistance Disputes')
@section('heading', 'Assistance Disputes')
@section('subheading', 'Distributions a farmer marked as not received. Read-only - the association recorded handing it out, the farmer disagrees, and this is where the office can see that disagreement.')

@section('header-actions')
    <a href="{{ route('mao.assistance-allocations.index') }}"
       class="inline-block rounded-lg border border-input bg-card px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
        Back to Allocations
    </a>
@endsection

@section('content')
<div>

    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-200">
        Distribution and receipt are two separate facts in this system: the association records handing
        something over, and the farmer separately confirms whether they actually received it. Neither ever
        overwrites the other, so when a farmer says "not received," it stays on record here until the
        office follows it up directly with the association.
    </div>

    {{-- Filter --}}
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <select name="association_id"
                class="min-w-48 rounded-lg border-2 border-primary px-4 py-2.5 text-sm font-medium text-foreground focus:outline-none">
            <option value="">All Associations</option>
            @foreach ($associations as $association)
                <option value="{{ $association->id }}" @selected(request('association_id') == $association->id)>
                    {{ $association->name }}
                </option>
            @endforeach
        </select>

        <button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0a2f15]">
            Filter
        </button>

        @if (request()->hasAny(['association_id']))
            <a href="{{ route('mao.assistance-allocations.disputes') }}"
               class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3 font-medium">Distribution</th>
                        <th class="px-5 py-3 font-medium">Farmer</th>
                        <th class="px-5 py-3 font-medium">Association</th>
                        <th class="px-5 py-3 font-medium">Assistance</th>
                        <th class="px-5 py-3 text-right font-medium">Quantity</th>
                        <th class="px-5 py-3 font-medium">Distributed On</th>
                        <th class="px-5 py-3 font-medium">Reported Not Received</th>
                        <th class="px-5 py-3 font-medium">Farmer's Note</th>
                        <th class="px-5 py-3 text-right font-medium">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @forelse ($disputes as $distribution)
                        <tr class="hover:bg-muted/60">
                            <td class="px-5 py-3 text-foreground">
                                DIST-{{ str_pad($distribution->id, 3, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="px-5 py-3 font-medium text-foreground">
                                {{ $distribution->farmer?->full_name ?? 'Unknown' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->allocation?->association?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->allocation?->assistance?->name
                                    ?? $distribution->in_kind_description
                                    ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-foreground">
                                {{ $distribution->quantity !== null ? number_format($distribution->quantity, 2) : '-' }}
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->distributed_at?->format('M d, Y') }}
                                <span class="block text-xs">by {{ $distribution->distributedBy?->display_name ?? '-' }}</span>
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $distribution->receipt_confirmed_at?->format('M d, Y g:i A') ?? '-' }}
                            </td>
                            <td class="px-5 py-3 max-w-xs text-muted-foreground">
                                {{ $distribution->receipt_note ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex justify-end gap-3">
                                    @if ($distribution->allocation)
                                        <a href="{{ route('mao.assistance-allocations.show', $distribution->allocation) }}"
                                           class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                            View Allocation
                                        </a>
                                    @endif
                                    @if ($distribution->damageReport)
                                        <a href="{{ route('mao.damage-reports.show', $distribution->damageReport) }}"
                                           class="text-sm font-medium text-sky-700 underline hover:text-sky-900">
                                            View Report
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-14 text-center">
                                <p class="text-sm font-medium text-muted-foreground">No disputes</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Every farmer who has answered so far has confirmed receiving their assistance.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $disputes->links() }}</div>
</div>
@endsection
