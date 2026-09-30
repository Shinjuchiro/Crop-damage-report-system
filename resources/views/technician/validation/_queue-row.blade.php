{{--
    ONE JOB IN THE VALIDATION QUEUE

    A row, not a card. The queue is a list of things to do in order, and a
    list is what that looks like.

    Expects:
        $report     a DamageReport, open (assigned or under_verification)
        $position   the number to print in the left gutter, or null for a
                    job already started (those are not queued, they are
                    half done, so a position would be misleading)

    The age figure is the one piece of colour on the row. A job sitting for
    a fortnight should not look the same as one assigned this morning, and
    colour is not carrying it alone: the number of days is written out.
--}}

@php
    $rowStarted = $report->status === 'under_verification';

    $assigned = $report->assigned_at ?? $report->created_at;
    $days     = $assigned ? (int) $assigned->diffInDays(now()) : null;

    /* Where the row goes. Not started: the briefing, so the comparison is
       read first. Started: back into the form where it was left off. */
    $rowHref = $rowStarted
        ? route('technician.inspection.edit', $report)
        : route('technician.validation.show', $report);

    $ageTone = match (true) {
        $days === null => 'text-muted-foreground',
        $days >= 14    => 'text-rose-700 dark:text-rose-400',
        $days >= 7     => 'text-amber-700 dark:text-amber-400',
        default        => 'text-muted-foreground',
    };

    $ageText = match (true) {
        $days === null => 'Assigned recently',
        $days === 0    => 'Assigned today',
        $days === 1    => 'Waiting 1 day',
        default        => 'Waiting ' . $days . ' days',
    };

    $rowCrops = $report->crops
        ->map(fn ($c) => $c->crop_specify ?: $c->crop?->name)
        ->filter()
        ->join(', ');

    $rowBarangay = $report->reportedBarangay?->name ?? $report->farmer?->barangay?->name;
@endphp

{{-- The row is not a link. It was one, with the action on the right looking
     like a button but doing nothing of its own: on a phone that button was
     a bare arrow, so the row's only clue that it went anywhere was an arrow
     with no word on it. The button is now the link and it is worded, which
     means the rest of the row does nothing when tapped. --}}
<li>
    <div class="flex items-start gap-3 px-4 py-4 sm:items-center sm:gap-4 sm:px-5">

        {{-- LEFT GUTTER
             A number for queued work, a filled marker for work in progress.
             Same width either way so the names below stay in one column. --}}
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold
                     {{ $rowStarted
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-border bg-muted text-muted-foreground' }}"
              aria-hidden="true">
            @if ($rowStarted)
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/>
                </svg>
            @else
                {{ $position }}
            @endif
        </span>

        {{-- THE JOB --}}
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-baseline gap-x-2">
                <span class="truncate text-[15px] font-bold leading-tight text-foreground">
                    {{ $report->farmer?->full_name ?? 'Unknown farmer' }}
                </span>
                <span class="shrink-0 text-xs font-semibold text-muted-foreground">{{ $report->reference }}</span>
            </span>

            <span class="mt-0.5 block truncate text-sm text-muted-foreground">
                {{ $rowBarangay ?: 'Location not set' }}
                @if ($rowCrops)
                    &middot; {{ $rowCrops }}
                @endif
            </span>

            <span class="mt-1 block text-xs font-semibold {{ $ageTone }}">
                @if ($rowStarted)
                    Started
                    {{ $report->validation?->inspection_started_at?->diffForHumans() ?? 'recently' }}
                @else
                    {{ $ageText }}
                @endif
            </span>
        </span>

        {{-- THE ACTION
             The only link on the row, so it carries a word at every width:
             the short form on a phone, where about 100px is all a 360px
             screen can spare next to the farmer's name, and the full one
             from sm up. 44px tall either way, a thumb sized target. --}}
        <a href="{{ $rowHref }}"
           class="ml-auto flex h-11 shrink-0 items-center gap-1.5 self-center rounded-lg px-3
                  text-sm font-semibold transition active:opacity-90
                  {{ $rowStarted ? 'bg-primary text-primary-foreground' : 'bg-accent text-accent-foreground' }}">
            <span class="lg:hidden">{{ $rowStarted ? 'Continue' : 'View' }}</span>
            <span class="hidden lg:inline">
                {{ $rowStarted ? 'Continue Inspection' : 'View Details' }}
            </span>
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 12h13M13 6l6 6-6 6"/>
            </svg>
            <span class="sr-only">
                {{ $rowStarted ? 'the inspection for' : 'the briefing for' }}
                {{ $report->reference }}
            </span>
        </a>
    </div>
</li>
