{{--
    <x-ui.card title="Recent damage reports" description="Last 30 days">
        <x-slot:actions>
            <x-ui.button size="sm" variant="outline" href="...">View all</x-ui.button>
        </x-slot:actions>

        ...content...

        <x-slot:footer>12 of 40 shown</x-slot:footer>
    </x-ui.card>

    Pass :padded="false" when the content is a table that should reach the
    edges of the card.
--}}
@props([
    'title'       => null,
    'description' => null,
    'padded'      => true,
])

{{--
    min-w-0 is load bearing, not decoration.

    A card is very often a grid or flex item, and those default to
    min-width: auto, which means they refuse to shrink below their
    min-content width. Any long unbroken run of text inside, a farmer's
    association name being the usual culprit, then forces the card wider than
    its own column. Because nothing in the app clips horizontal overflow at
    the body, that one card makes the whole page scroll sideways, and the
    first thing a person sees is a page shifted off to the right with the
    left edge cut off.

    truncate cannot save it on its own: white-space: nowrap still reports the
    full string as the min-content width, so the card is already too wide
    before the ellipsis ever gets a chance to apply. min-width: 0 is what lets
    the card shrink so truncate can do its job.

    Measured on a phone-width viewport: a card holding "Tres Cruses Agrarian
    Reform Beneficiaries Farmers Association, Inc." rendered 466px wide in a
    351px column and pushed the document to 478px. With this class it is
    351px and the document is 375px.

    Harmless everywhere else: for a normal block level element min-width: auto
    already resolves to 0, so this only changes behaviour where it needs to.
--}}
<div {{ $attributes->class('min-w-0 rounded-xl border border-border bg-card text-card-foreground shadow-sm') }}>

    @if ($title || $description || isset($actions))
        {{-- No rule under the header any more: in the reference the title
             just sits above its content with air between them, and a card
             that is already separated by its own edge does not need a
             second line inside it. --}}
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 pb-1 pt-4 sm:px-5 sm:pt-5">
            <div class="min-w-0">
                {{-- Sentence case and bold, as in the reference, rather than
                     the small capitals this used to use. Capitals read as a
                     form label; a bold sentence reads as a heading, and it is
                     what sits above "Recent Reports" in the design we are
                     following. --}}
                @if ($title)
                    <h3 class="text-lg font-bold leading-tight tracking-tight">{{ $title }}</h3>
                @endif

                @if ($description)
                    <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-4 sm:p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground sm:px-5">
            {{ $footer }}
        </div>
    @endisset
</div>
