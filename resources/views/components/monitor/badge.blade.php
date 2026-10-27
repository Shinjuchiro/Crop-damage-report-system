{{--
    The small status pills the three monitoring pages share.

        <x-monitor.badge kind="assignment" value="assigned" />
        <x-monitor.badge kind="match" value="matched" />
        <x-monitor.badge kind="validation" value="approved" />

    Assignment, planting match and validation are three separate facts and
    are deliberately three separate pills (never one combined status).
--}}
@props(['kind', 'value' => null])

@php
    [$label, $tone] = match ($kind) {
        'assignment' => $value === 'assigned'
            ? ['Assigned', 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300']
            : ['Not Assigned', 'bg-secondary text-foreground'],

        /* The three planting-match results. None of them is a verdict on the
           report: a missing record only means nothing on file matches. */
        'match' => match ($value) {
            'matched'  => ['Matching Record Found', 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300'],
            'mismatch' => ['No Matching Planting Record', 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],
            default    => ['No Planting Record Found', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
        },

        /* The dates on a report against the official disaster date. Only
           'review' is ever shown as a pill: a report whose dates agree needs
           no badge. It is a prompt for the office, never a verdict, and the
           report is not rejected because of it. */
        'dates' => $value === 'reviewed'
            ? ['Dates Reviewed', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300']
            : ['Dates Need Review', 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'],

        /* The office's review of a submitted technician assessment. A
           technician finishing an inspection leaves it Pending Review; only
           the office's decision makes it Approved or Rejected. "Assigned" is
           never a validation result. */
        'validation' => match ($value) {
            'approved' => ['Approved', 'bg-lime-100 text-lime-800 dark:bg-lime-950 dark:text-lime-300'],
            'rejected' => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
            default    => ['Pending Review', 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'],
        },

        default => [ucfirst((string) $value), 'bg-secondary text-foreground'],
    };
@endphp

<span {{ $attributes->class('inline-flex whitespace-nowrap rounded px-2.5 py-1 text-xs font-medium ' . $tone) }}>{{ $label }}</span>
