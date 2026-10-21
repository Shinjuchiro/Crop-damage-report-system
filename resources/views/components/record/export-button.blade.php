{{--
    EXPORT REPORT

        <x-record.export-button />

    The button and its two-format menu, for the Crop Management Report tabs:
    the office's three and the association's two, plus the assistance lists.
    It used to sit at the end of the tab strip; it now sits on the
    filter row, beside the search box, which is where the design puts it and
    where it belongs: it exports what the filters on that row are showing, so
    standing next to them says so without a word of explanation.

    WHY A MENU AND NOT TWO BUTTONS

    Excel and PDF are the same action in two formats, not two actions. Two
    buttons would compete for the same corner and double the width of a row
    that already carries three filters and a search field.

    WHAT IT ACTUALLY LINKS TO

    This page's own URL with ?export=xlsx or ?export=pdf added, built with
    fullUrlWithQuery, so every filter already in the query string comes along
    without this component knowing what any of them are. The controller
    answers that parameter before it builds the page. ?page and ?selected are
    dropped: an export covers every matching record, not the page on screen,
    and the open record has nothing to do with the file.

    A download does not navigate, so the parameter never lands in the page's
    own address.
--}}

<div x-data="{ open: false }" @keydown.escape.window="open = false"
     {{ $attributes->class('relative shrink-0') }}>

    <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
            class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4
                   text-sm font-semibold text-primary-foreground shadow-sm transition-colors
                   hover:bg-[#0d3d1b] sm:w-auto">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
        </svg>
        Export Report
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </button>

    {{-- left-0 on a phone, right-0 from sm up: on a narrow screen the button
         is full width and the menu lines up under its left edge; on a wide
         one the button sits mid-row and a menu hanging right stays inside
         the card instead of off the edge of it. --}}
    <div x-show="open" x-cloak @click.outside="open = false"
         class="absolute left-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-border
                bg-card shadow-lg sm:left-auto sm:right-0">

        <p class="border-b border-border px-4 py-2.5 text-xs text-muted-foreground">
            Exports every record matching the filters now on screen.
        </p>

        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx', 'selected' => null, 'page' => null]) }}"
           class="flex items-start gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted/60">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM9 12l6 6M15 12l-6 6"/>
            </svg>
            <span>
                <span class="block font-semibold text-card-foreground">Excel (.xlsx)</span>
                <span class="block text-xs text-muted-foreground">To sort, filter and total</span>
            </span>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf', 'selected' => null, 'page' => null]) }}"
           class="flex items-start gap-3 border-t border-border px-4 py-3 text-sm transition-colors hover:bg-muted/60">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM8 13h8M8 17h5"/>
            </svg>
            <span>
                <span class="block font-semibold text-card-foreground">PDF</span>
                <span class="block text-xs text-muted-foreground">To print, sign or attach</span>
            </span>
        </a>
    </div>
</div>
