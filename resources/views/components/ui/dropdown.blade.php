{{--
    <x-ui.dropdown>
        <x-slot:trigger>
            <x-ui.button variant="outline" size="sm">Actions</x-ui.button>
        </x-slot:trigger>

        <x-ui.dropdown-item href="...">View details</x-ui.dropdown-item>
        <x-ui.dropdown-item variant="destructive" @click="...">Archive</x-ui.dropdown-item>
    </x-ui.dropdown>
--}}
@props([
    'align' => 'right',
    'width' => 'w-52',
])

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false"
     {{ $attributes->class('relative inline-block') }}>

    <div @click="open = !open" :aria-expanded="open" class="contents">
        {{ $trigger }}
    </div>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click="open = false"
         class="absolute z-50 mt-2 {{ $width }} origin-top overflow-hidden rounded-lg border border-border
                bg-popover p-1 text-popover-foreground shadow-lg
                {{ $align === 'left' ? 'left-0' : 'right-0' }}">
        {{ $slot }}
    </div>
</div>
