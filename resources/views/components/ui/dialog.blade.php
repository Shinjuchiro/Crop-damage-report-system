{{--
    A dialog you open from anywhere on the page by name.

    <x-ui.button @click="$dispatch('open-dialog', 'assign-technician')">Assign</x-ui.button>

    <x-ui.dialog name="assign-technician" title="Assign a technician"
                 description="They will be notified straight away.">
        ...form fields...

        <x-slot:footer>
            <x-ui.button variant="outline" @click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit">Assign</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    This is for gathering input. It is NOT the confirmation step: a form
    inside here still needs its data-confirm attributes, so the person sees
    what they entered before it is saved.

    Note it is deliberately rendered where it is written. If a parent element
    has a transform on it, a fixed dialog is positioned against that parent
    instead of the window, so keep it out of animated or transformed wrappers.
--}}
@props([
    'name',
    'title'       => null,
    'description' => null,
    'size'        => 'md',
])

@php
    $widths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
@endphp

<div x-data="{
        open: false,
        close() { this.open = false; },
     }"
     @open-dialog.window="if ($event.detail === '{{ $name }}') { open = true; $nextTick(() => $refs.panel.focus()); }"
     @close-dialog.window="if ($event.detail === '{{ $name }}') open = false"
     @keydown.escape.window="open && close()"
     x-cloak>

    <div x-show="open"
         x-transition.opacity.duration.150ms
         class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-900/55 p-0 sm:items-center sm:p-4"
         role="dialog" aria-modal="true">

        <div x-show="open"
             x-ref="panel"
             tabindex="-1"
             @click.outside="close()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="max-h-[90vh] w-full {{ $widths[$size] ?? $widths['md'] }} overflow-y-auto
                    rounded-t-2xl bg-card text-card-foreground shadow-2xl outline-none sm:rounded-xl">

            @if ($title || $description)
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    @if ($title)
                        <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
                    @endif
                </div>
            @endif

            <div class="px-5 py-5 sm:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-col-reverse gap-2 border-t border-border bg-muted/50 px-5 py-4
                            sm:flex-row sm:justify-end sm:px-6">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
