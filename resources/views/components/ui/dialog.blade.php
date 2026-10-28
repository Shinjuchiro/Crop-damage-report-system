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

{{--
    lock/unlock keep the page behind this dialog from scrolling while it is
    open, so dragging on the backdrop on a phone does not move whatever is
    underneath. The counter is shared on window with the global confirmation
    dialog, which often opens on top of this one: without it, whichever
    closed first would unlock the body while the other was still showing.
    Opening and closing each go through one method, so the count cannot drift
    out of step with what is actually on screen.
--}}
<div x-data="{
        open: false,

        lock() {
            window.__openDialogCount = (window.__openDialogCount || 0) + 1;
            document.body.style.overflow = 'hidden';
        },

        unlock() {
            window.__openDialogCount = Math.max(0, (window.__openDialogCount || 1) - 1);

            if (! window.__openDialogCount) {
                document.body.style.overflow = '';
            }
        },

        show() {
            if (this.open) {
                return;
            }

            this.open = true;
            this.lock();
            this.$nextTick(() => this.$refs.panel && this.$refs.panel.focus());
        },

        close() {
            if (! this.open) {
                return;
            }

            this.open = false;
            this.unlock();
        },
     }"
     @open-dialog.window="if ($event.detail === '{{ $name }}') show()"
     @close-dialog.window="if ($event.detail === '{{ $name }}') close()"
     @keydown.escape.window="open && close()"
     x-cloak>

    <div x-show="open"
         x-transition.opacity.duration.150ms
         style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:90;background-color:rgba(15,23,42,0.55);"
         class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-900/55 p-0 sm:items-center sm:p-4"
         role="dialog" aria-modal="true">

        <div x-show="open"
             x-ref="panel"
             tabindex="-1"
             @click.outside="close()"
             x-transition.opacity.duration.150ms
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
