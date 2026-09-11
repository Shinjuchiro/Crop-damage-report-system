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

<div {{ $attributes->class('rounded-xl border border-border bg-card text-card-foreground shadow-sm') }}>

    @if ($title || $description || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3 sm:px-5 sm:py-4">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-base font-semibold leading-tight tracking-tight">{{ $title }}</h3>
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
