{{--
    REPORTS & ANALYTICS - the two halves

    The technician's sidebar has one entry for this (revision section 3), and
    it covers two pages that were separate items before:

        Summaries           their own figures: how many inspections, how long
                            each took, what severities they have been assessing
        Inspection History  the inspections themselves, newest first

    They stay two routes and two pages. This strip is what makes them read as
    one destination: whichever you are on, the other is one tap away, and the
    sidebar item stays lit for both.

        @include('technician.partials.analytics-tabs')

    at the top of each page's content.
--}}

@php
    $analyticsTabs = [
        ['label' => 'Summaries',          'route' => 'technician.summaries.index', 'pattern' => 'technician.summaries.*'],
        ['label' => 'Inspection History', 'route' => 'technician.history.index',   'pattern' => 'technician.history.*'],
    ];
@endphp

<div class="mb-4 flex flex-wrap gap-2">
    @foreach ($analyticsTabs as $tab)
        @php $active = request()->routeIs($tab['pattern']); @endphp

        <a href="{{ route($tab['route']) }}"
           @if ($active) aria-current="page" @endif
           class="rounded-lg px-3.5 py-2 text-sm font-medium transition
                  {{ $active
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'border border-input text-muted-foreground hover:bg-muted/60 hover:text-foreground' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
