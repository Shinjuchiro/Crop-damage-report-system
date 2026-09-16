{{--
    Right-hand panel of every "list + detail" page (Archive and the other
    pages redesigned alongside it in Sept 2026). The list card sits to the
    left; this sits to the right on desktop and takes over the whole screen
    on a phone once something is selected - see the class flip below.

    <x-ui.detail-panel :selected="$selected" back-url="{{ url()->current() }}"
                        empty-text="Select a record to view its details.">
        ...detail content, only rendered when $selected is truthy...
    </x-ui.detail-panel>

    back-url is the current page's URL with ?selected stripped - the "Back
    to list" link a phone needs once a record fills the screen (hidden on
    lg screens, where the list is visible right next to the panel anyway).
--}}
@props([
    'selected'  => null,
    'backUrl'   => null,
    'emptyText' => 'Select a record to view its details.',
])

<div {{ $attributes->class([
        'rounded-xl border border-border bg-card shadow-sm lg:sticky lg:top-20',
        $selected ? 'block' : 'hidden lg:block',
    ]) }}>
    @if ($selected)
        <div class="p-4 sm:p-5">
            @if ($backUrl)
                <a href="{{ $backUrl }}"
                   class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground lg:hidden">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to list
                </a>
            @endif

            {{ $slot }}
        </div>
    @else
        <div class="flex min-h-[16rem] flex-col items-center justify-center gap-2 p-8 text-center">
            <svg class="h-10 w-10 text-muted-foreground/40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-medium text-muted-foreground">{{ $emptyText }}</p>
        </div>
    @endif
</div>
