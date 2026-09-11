{{--
    What a list shows before there is any data. Every monitoring page will
    look like this until the Farmer module starts producing records, so it is
    worth it being informative rather than just saying "No results".

    <x-ui.empty title="No damage reports yet"
                message="Reports appear here once a farmer submits one.">
        <x-ui.button href="...">Add a farmer</x-ui.button>
    </x-ui.empty>
--}}
@props([
    'title'   => 'Nothing here yet',
    'message' => null,
    'icon'    => 'M9 13h6m-6 4h4M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5z',
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-5 py-10 text-center sm:py-14') }}>

    <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="{{ $icon }}"/>
        </svg>
    </span>

    <p class="text-base font-semibold text-foreground">{{ $title }}</p>

    @if ($message)
        <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-muted-foreground">{{ $message }}</p>
    @endif

    @if (trim($slot))
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
