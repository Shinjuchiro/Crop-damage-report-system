{{--
    THE DETAIL MODAL

    Every "list + detail" page in the system shows a record here: Farmers,
    Membership Applications, Damage Reports, Validation, Assistance, the
    Archive, and the rest.

        <x-ui.detail-panel :selected="$selected" back-url="{{ url()->current() }}"
                            empty-text="Select a record to view its details.">
            ...detail content, only rendered when $selected is truthy...
        </x-ui.detail-panel>

    A MODAL, NOT A SIDE PANEL

    This used to be a 380px column pinned to the right of the list. Three
    things were wrong with that. The record got a third of the screen while
    two thirds went to a list you had already finished reading. Anything
    long - a farmer's full profile, a damage report with photos - was a
    narrow ribbon you scrolled forever. And when nothing was selected, a
    large empty box sat there holding the space open.

    As a modal the record gets the middle of the screen at a readable
    width, the list stays put underneath, and nothing is reserved for it
    when it is closed.

    STILL SERVER RENDERED, STILL A URL

    Selection is ?selected=<id> on the page's own URL, exactly as before.
    That is deliberate and worth keeping: the open record survives a
    refresh, it can be linked to or sent to a colleague, and the browser's
    Back button closes it. So this is a modal in appearance only - there is
    no client-side fetching, and closing it is a normal navigation to
    back-url, which is the same page with ?selected stripped.

    Escape and a click on the dim both follow that same link, so there is
    one way out and it cannot fall out of step with the URL.

    empty-text is still accepted so no call site breaks, but it is no
    longer drawn: an empty panel was a thing to look at, and a modal that
    is not open is simply not there.
--}}
@props([
    'selected'  => null,
    'backUrl'   => null,
    'emptyText' => 'Select a record to view its details.',
])

@php
    // Without a back-url there is nothing to navigate to on close, so the
    // dim and the Escape key would trap the user. Falling back to the
    // current URL at least reloads the page without the selection.
    $closeUrl = $backUrl ?: url()->current();
@endphp

@if ($selected)
    <div class="fixed inset-0 z-[80] flex items-end justify-center overflow-y-auto p-0 sm:items-center sm:p-6"
         role="dialog"
         aria-modal="true"
         x-data
         @keydown.escape.window="window.location = '{{ $closeUrl }}'">

        {{-- The dim. Its own layer rather than a background on the flex
             container, so a click lands on it and not on the panel. --}}
        <a href="{{ $closeUrl }}"
           class="absolute inset-0 bg-slate-900/60"
           aria-label="Close"></a>

        {{-- CENTRED, AND WHY A LONG ONE STILL LOOKS RIGHT

             This used to be pinned to the top (sm:items-start), which left a
             short record - an association with three figures and three
             buttons - floating under a tall band of dimmed page with
             nothing in it.

             It is centred now, and the max-h below is what makes that safe
             for a long record too. The panel never grows past 86vh: past
             that its own body scrolls inside, so a long record is a tall
             box with a small even gap above and below it rather than
             something centred so far down that its top is off-screen. That
             is the usual trap with centring a modal, and the height cap is
             what avoids it - without the cap, a record taller than the
             window would have its heading pushed above the top edge where
             it could never be scrolled back to.

             The cap earns its keep twice over: the heading and the close
             button stay put while the body scrolls, so there is always a
             way out in view, and the list underneath keeps its own scroll
             position, so closing puts you back at the row you opened
             rather than at the top of the page.

             On a phone it stays a bottom sheet (items-end), which is where
             a thumb is. --}}
        <div {{ $attributes->class(
                'relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden '
                . 'rounded-t-2xl border border-border bg-card shadow-2xl sm:max-h-[86vh] sm:rounded-2xl'
            ) }}>

            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
                <span class="text-sm font-semibold text-muted-foreground">Record details</span>

                <a href="{{ $closeUrl }}"
                   class="-mr-1 inline-flex h-9 w-9 items-center justify-center rounded-full
                          text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                   aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </a>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
