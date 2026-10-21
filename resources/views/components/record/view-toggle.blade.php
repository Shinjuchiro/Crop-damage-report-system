{{--
    BY FARMER, OR ALL RECORDS

        <x-record.view-toggle :view="$view" />

    The switch at the top of every record list: Crop Planted, Crop Damage,
    Validation, for the office and for an association.

    Grouped by farmer is what opens, because the revision's whole point is
    that a list should say "here is this member and their records" rather
    than printing the same name down the page (sections 6 and 10 to 12).

    All records is the flat, newest-first list it used to be, and it is kept
    because the office also works the other way round: "which reports came
    in today", "what is still unassigned". Finding those by reading down a
    list grouped by farmer would be worse, not better.

    Links, not script. Each view is a different ordering from the database,
    so each one is its own URL, bookmarkable and reachable with Back. Every
    other filter in the query string survives the switch; the page number
    does not, because page 3 of one ordering is not page 3 of the other.
--}}

@props(['view' => 'grouped'])

@php
    $carry   = request()->except(['view', 'page']);
    $query   = fn (array $extra = []) => url()->current()
        . (($params = array_filter($carry + $extra, fn ($v) => $v !== null && $v !== ''))
            ? '?' . http_build_query($params) : '');

    $grouped = $view !== 'flat';

    $pill = 'rounded-full px-3.5 py-1.5 text-sm font-semibold transition-colors';
    $on   = $pill . ' bg-primary text-primary-foreground shadow-sm';
    $off  = $pill . ' border border-input text-muted-foreground hover:bg-muted/60 hover:text-foreground';
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <a href="{{ $query() }}" class="{{ $grouped ? $on : $off }}"
       @if ($grouped) aria-current="page" @endif>
        By farmer
    </a>

    <a href="{{ $query(['view' => 'flat']) }}" class="{{ $grouped ? $off : $on }}"
       @if (! $grouped) aria-current="page" @endif>
        All records
    </a>
</div>
