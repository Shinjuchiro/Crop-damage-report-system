{{--
    THE CROP MANAGEMENT REPORT HEADER

        <x-crop-management.header tab="damage">
            <div class="grid gap-4 sm:grid-cols-3"> ... stat cards ... </div>
        </x-crop-management.header>

    The heading, the tab strip, and whatever figures the page passes in.

    ONE SCREEN, THREE TABS

    Crop Planted, Crop Damage and Validation Monitoring used to be three
    pages reached from a hub of cards, so getting from one to another meant
    going back out and in again. They are one screen now: the strip is the
    same on all three, only the active tab and the table beneath it change.
    The hub page is gone, and the sidebar item opens the first tab.

    They are still three URLs, not one page with script. Each tab carries its
    own filters, its own sort and its own page number in the query string, so
    a filtered list can be bookmarked, sent to a colleague, and reached with
    the browser's Back button. A tab that only existed in Alpine state would
    lose all three.

    THE FIGURES ARE THE PAGE'S OWN

    This component no longer carries three shared totals of its own. Each
    page passes its own stat row in as the slot, and it is rendered between
    the heading and the tabs. One row of numbers, belonging to the tab that
    is open, instead of two rows where the first repeated what the tabs
    already said.

    THE SAME STRIP FOR THE ASSOCIATION

    An association officer gets the same report with two tabs rather than
    three (no validation screen for that role), and the subtitle changes with
    it. Pass role="association" and the strip renders two equal columns
    instead of three. The markup is shared on purpose: this is one section of
    the system seen by two roles, and a copy of this file for the second one
    would be a second place to restyle every time the first changes.

    props
      tab     which tab is active: planting, damage or (MAO only) validation
      role    'mao' (default) or 'association'

    EXPORT REPORT IS NOT HERE ANY MORE

    It used to sit at the end of this tab strip. It now lives on each page's
    own filter row, beside the search box (see x-record.export-button): it
    exports what those filters are showing, so standing next to them says so
    without a word of explanation.
--}}

@props(['tab', 'role' => 'mao'])

@php
    $tabs = \App\Support\CropManagement::tabs($role);

    // Each tab's own "new to you" count; opening a record lowers it.
    $assocId = $role === 'association'
        ? \App\Models\AssociationOfficer::where('user_id', auth()->id())->value('association_id')
        : null;
    $tabCounts = auth()->user() ? \App\Support\Seen::counts(auth()->user(), $assocId) : [];
    $countKey = ['planting' => 'planting', 'damage' => 'damage', 'validation' => 'validation'];

    /* Tailwind compiles the classes it can see in the source, so the column
       count cannot be interpolated from count($tabs) - that class would
       never be generated and the strip would fall back to one column. Both
       spellings are written out here instead. */
    $columns = count($tabs) === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3';

    $blurb = $role === 'association'
        ? 'Monitor what your members have planted, reported damaged, and had inspected.'
        : 'Monitor crop planting records, crop damage reports, and field validation in one place.';
@endphp

<div class="mb-5">

    {{-- The page's own heading. layouts.app's is switched off on each of
         these pages, via the hideHeading section, so the title can sit here
         above the strip and read as one screen rather than a heading per
         tab. --}}
    <div class="mb-4">
        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Crop Management Report
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ $blurb }}
        </p>
    </div>

    {{-- THE FIGURES FOR THE TAB YOU ARE ON

         Each page passes its own stat row in here, and they sit above the
         tabs rather than below them.

         There used to be three shared totals in this spot - one per tab -
         with the page's own figures underneath. Two rows of numbers stacked
         on top of each other, the first of which repeated what the tabs
         already said, so the eye had to travel past six figures to reach
         the list. One row now, and it is the one that belongs to whichever
         tab is open. --}}
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif

    {{-- THE TABS: three for the office, two for an association --}}
    <div class="mt-4">

        <nav class="grid grid-cols-1 gap-2 {{ $columns }}" aria-label="Crop Management Report sections">
            @foreach ($tabs as $key => $item)
                @php $active = $key === $tab; @endphp

                <a href="{{ route($item['route']) }}"
                   @if ($active) aria-current="page" @endif
                   class="flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold
                          transition-colors
                          {{ $active
                              ? 'bg-primary text-primary-foreground shadow-sm'
                              : 'border border-border bg-card text-muted-foreground hover:bg-muted/60 hover:text-foreground' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                    @if (! empty($tabCounts[$key]))
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-bold leading-none text-destructive-foreground"
                              title="{{ $tabCounts[$key] }} new to you">{{ $tabCounts[$key] > 99 ? '99+' : $tabCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

    </div>
</div>
