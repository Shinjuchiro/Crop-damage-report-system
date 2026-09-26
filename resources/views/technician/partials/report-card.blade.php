{{--
    ONE ASSIGNED REPORT, AS A CARD (phone widths)

    Used by two pages:

        technician/dashboard.blade.php        the first six rows of the queue
        technician/reports/index.blade.php    the full list, with filters

    Those two lists were previously written out twice, which is how they
    drifted: the dashboard card showed the association, the damage figure and
    the photo count, the Assigned Reports card showed none of them. One
    partial means a change to the card is a change to both pages.

    Both controllers load exactly the same relations and the same
    withCount('photos'), so nothing here can fire an N+1 query on either page.

    Expects:
        $report              a DamageReport with farmer, reportedBarangay,
                             crops.crop, validation and photos_count loaded
        $showPlantingMatch   optional, default false. Only the Assigned
                             Reports page computes hasPlantingMatch on its
                             records, so only that page asks for the line.

    LAYOUT

    A card per report rather than a table row, because the technician screens
    are mobile first (proposal section 82) and the table needs about 670px
    before the one control that matters comes into view.

    The figures sit in labelled blocks with an icon apiece instead of running
    on as "Barangay: X, Submitted: Y" text, so a technician can find the one
    number they came for without reading the whole card. Left pair is about
    the farm, right pair is about the paperwork, with a rule between them.
--}}

@php
    $showPlantingMatch = $showPlantingMatch ?? false;

    /* An inspection that has been started goes back to where it was left
       off; anything else opens read only. Same rule the table uses. */
    $cardHref = $report->status === 'under_verification'
        ? route('technician.inspection.edit', $report)
        : route('technician.reports.show', $report);

    /* Section 45: the technician's assessed figure once the inspection is
       in, the farmer's estimate before that, and never the two confused
       with each other, so the card says which one it is showing. */
    $cardAssessed = $report->validation?->assessed_damage_percent;
    $cardEstimate = $report->crops->avg('estimated_damage_percent');
    $cardShown    = $cardAssessed ?? $cardEstimate;

    /* The bar down the left edge. It follows the status badge on the right,
       using the same colour families x-ui.badge gives that status, so the
       edge and the badge can never tell the technician two different things.
       The bar is decoration: every status it stands for is also written out
       in words in the badge, so nothing here is carried by colour alone. */
    $cardAccent = match ($report->status) {
        'assigned', 'under_verification' => 'bg-sky-500',
        'verified', 'approved'           => 'bg-green-600',
        'rejected', 'flagged'            => 'bg-rose-500',
        'pending'                        => 'bg-amber-500',
        default                          => 'bg-slate-300 dark:bg-slate-600',
    };

    $cardAction = match ($report->status) {
        'assigned'           => 'View Details',
        'under_verification' => 'Continue Inspection',
        default              => 'View Details',
    };
@endphp

<a href="{{ $cardHref }}"
   class="relative block overflow-hidden rounded-xl border border-border bg-card pl-4 transition active:bg-muted">

    {{-- The status bar. aria-hidden because the badge below already says it --}}
    <span class="absolute inset-y-0 left-0 w-1.5 {{ $cardAccent }}" aria-hidden="true"></span>

    <div class="min-w-0 p-3.5">

        {{-- Reference and status --}}
        <div class="flex items-start justify-between gap-2">
            <span class="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold tracking-wide text-foreground">
                {{ $report->reference }}
            </span>
            <x-ui.status :value="$report->status" />
        </div>

        {{-- Who --}}
        <p class="mt-2 truncate text-base font-bold leading-tight text-foreground">
            {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
        </p>
        <p class="truncate text-xs text-muted-foreground">
            {{ $report->farmer?->association?->name ?? 'No association' }}
        </p>

        {{-- The four figures.

             Two by two rather than the mockup's 60/40 split with the pair of
             figures crammed into the right hand third. On a 360px phone that
             right hand column works out to about 76px of usable width, which
             is not enough for "Sep 16, 2026" to stay on one line. Equal
             columns give each figure about 136px, and the rule down the
             middle keeps the banded look the mockup has.

             min-w-0 on every block so a long barangay name wraps inside its
             own column instead of widening the whole card. --}}
        <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-border pt-3">

            {{-- Barangay --}}
            <div class="flex min-w-0 gap-1.5">
                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 21s6.5-5.3 6.5-10.5a6.5 6.5 0 10-13 0C5.5 15.7 12 21 12 21z"/>
                    <circle cx="12" cy="10.5" r="2.1"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[11px] leading-tight text-muted-foreground">Barangay</p>
                    <p class="text-sm font-semibold leading-tight text-foreground">
                        {{ $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name ?? '-' }}
                    </p>
                </div>
            </div>

            {{-- Damage. Right hand column, so it carries the dividing rule. --}}
            <div class="flex min-w-0 gap-1.5 border-l border-border pl-3">
                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3.8 18a9 9 0 1116.4 0"/>
                    <path d="M12 18l4.2-5.4"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[11px] leading-tight text-muted-foreground">Damage</p>
                    <p class="text-sm font-semibold leading-tight text-foreground">
                        @if ($cardShown === null)
                            Not assessed
                        @else
                            {{ round($cardShown) }}%
                            <span class="block text-[11px] font-normal text-muted-foreground">
                                {{ $cardAssessed !== null ? 'you assessed' : 'farmer estimate' }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Submitted --}}
            <div class="flex min-w-0 gap-1.5">
                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 15V3.5M12 3.5L8.2 7.3M12 3.5l3.8 3.8"/>
                    <path d="M4.5 15v3.5A1.5 1.5 0 006 20h12a1.5 1.5 0 001.5-1.5V15"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[11px] leading-tight text-muted-foreground">Submitted</p>
                    <p class="text-sm font-semibold leading-tight text-foreground">
                        {{ $report->created_at?->format('M d, Y') ?? '-' }}
                    </p>
                </div>
            </div>

            {{-- Photos --}}
            <div class="flex min-w-0 gap-1.5 border-l border-border pl-3">
                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                     stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 6.5h11.5a1 1 0 011 1v9a1 1 0 01-1 1H8a1 1 0 01-1-1v-9a1 1 0 011-1z"/>
                    <path d="M4 9.5v9a1 1 0 001 1h11"/>
                    <path d="M20.5 14.5L17 11l-5 5"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-[11px] leading-tight text-muted-foreground">Photos</p>
                    <p class="text-sm font-semibold leading-tight text-foreground">
                        {{ $report->photos_count ?? 0 }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Crops --}}
        @if ($report->crops->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-1">
                @foreach ($report->crops as $crop)
                    <x-ui.badge variant="primary">{{ $crop->crop_specify ?: $crop->crop?->name }}</x-ui.badge>
                @endforeach
            </div>
        @endif

        {{-- Section 62's cross-check. A match signal only, never an
             eligible/ineligible label: a report with no matching planting
             record is still fully available to inspect. --}}
        @if ($showPlantingMatch)
            <p class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                @if ($report->hasPlantingMatch)
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    Matching planting record on file
                @else
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 8v5M12 16.5h.01M12 3.5L2.8 19.2A1 1 0 003.7 20.7h16.6a1 1 0 00.9-1.5L12 3.5z"/>
                    </svg>
                    No matching planting record
                @endif
            </p>
        @endif

        {{-- The action. Full width and 44px tall so it is a thumb sized
             target, which the rest of the card is too: the whole card is the
             link, this bar just says where it goes. --}}
        <span class="mt-3 flex h-11 items-center gap-2 rounded-lg bg-accent px-3
                     text-sm font-semibold text-accent-foreground">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h13M13 6l6 6-6 6"/>
            </svg>
            {{ $cardAction }}
        </span>
    </div>
</a>
