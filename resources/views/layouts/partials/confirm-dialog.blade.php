{{--
    The single confirmation dialog for the whole system.

    It is included once in each layout. Nothing on a page has to build its own
    modal: any form or link that carries a data-confirm attribute is routed here
    by the listener in resources/js/app.js, and the action only reaches the
    server after the person presses Confirm.

    Deliberately large text, generous spacing and full width buttons on a phone,
    because a good number of the people using this are elderly farmers reading
    it outdoors on a small screen.
--}}
<div x-data="{
        open: false,
        title: '',
        message: '',
        detail: '',
        action: 'Confirm',
        tone: 'default',
        review: [],
        proceed: null,

        /*
         | Stop the page behind the dialog scrolling while it is open.
         |
         | On a touch screen, dragging anywhere on the dim backdrop scrolls
         | whatever is underneath, so the person is left somewhere else
         | entirely once the dialog closes.
         |
         | The count lives on window rather than in this component, because
         | this dialog routinely opens on top of an x-ui.dialog. Without it,
         | whichever closed first would unlock the body while the other was
         | still open.
         */
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

        show(detail) {
            this.title   = detail.title;
            this.message = detail.message;
            this.detail  = detail.detail;
            this.action  = detail.action;
            this.tone    = detail.tone;
            this.review  = detail.review || [];
            this.proceed = detail.proceed;

            // Guarded so a second request while one is already showing does
            // not lock twice and leave the page stuck after it closes.
            if (! this.open) {
                this.open = true;
                this.lock();
            }

            this.$nextTick(() => this.$refs.cancel && this.$refs.cancel.focus());
        },

        cancel() {
            if (! this.open) {
                return;
            }

            this.open    = false;
            this.proceed = null;
            this.unlock();
        },

        confirm() {
            if (! this.open) {
                return;
            }

            const run = this.proceed;

            this.open    = false;
            this.proceed = null;
            this.unlock();

            if (run) {
                run();
            }
        },
     }"
     @confirm-request.window="show($event.detail)"
     @keydown.escape.window="open && cancel()"
     x-cloak>

    {{--
        z-[100] puts this above everything the app itself draws: the sidebar
        is z-40, the drawer backdrop z-30, the top bar z-20 and the phone
        bottom bar z-30. Leaflet is handled separately, in app.css, by giving
        the map container its own stacking context so its internal z-index of
        400 stops competing with the rest of the page.
    --}}
    <div x-show="open"
         class="fixed inset-0 z-[100] flex items-end justify-center overflow-y-auto p-0 sm:items-center sm:p-4"
         role="dialog"
         aria-modal="true"
         :aria-label="title">

        {{-- The dim, as its own layer rather than a background colour on the
             flex container. Clicking it cancels, which is what a tap outside
             a dialog should do. --}}
        <div x-show="open"
             x-transition.opacity.duration.150ms
             @click="cancel()"
             class="absolute inset-0 bg-slate-900/60"
             aria-hidden="true"></div>

        {{-- relative, so the panel sits above the dim rather than under it --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="relative w-full max-w-lg overflow-hidden rounded-t-2xl bg-card text-card-foreground shadow-2xl sm:rounded-2xl">

            {{-- Heading --}}
            <div class="flex items-start gap-4 px-5 pt-6 sm:px-7">
                <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                      :class="tone === 'danger' ? 'bg-destructive/10 text-destructive' : 'bg-accent text-accent-foreground'">
                    {{-- Two separate icons rather than a template inside the svg,
                         which browsers do not parse the way you would expect. --}}
                    <svg x-show="tone === 'danger'" class="h-6 w-6" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                    </svg>
                    <svg x-show="tone !== 'danger'" class="h-6 w-6" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 22a10 10 0 100-20 10 10 0 000 20zM12 8v5m0 3h.01"/>
                    </svg>
                </span>

                <div class="min-w-0">
                    <h2 class="text-lg font-bold leading-snug text-foreground sm:text-xl" x-text="title"></h2>
                    <p class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground" x-text="message"></p>
                </div>
            </div>

            {{-- Review of what is about to happen --}}
            <template x-if="review.length">
                {{-- Capped and scrollable. Some of these lists run to eight or
                     nine rows, and on a phone that would push Cancel and
                     Confirm off the bottom of the screen. --}}
                <div class="mx-5 mt-5 max-h-[40vh] overflow-y-auto rounded-xl border border-border
                            bg-muted px-4 py-3 sm:mx-7">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Please review
                    </p>
                    <dl class="divide-y divide-border">
                        <template x-for="row in review" :key="row.label">
                            <div class="flex flex-col gap-0.5 py-2 sm:flex-row sm:justify-between sm:gap-6">
                                <dt class="text-sm text-muted-foreground" x-text="row.label"></dt>
                                <dd class="text-sm font-semibold text-foreground sm:text-right" x-text="row.value"></dd>
                            </div>
                        </template>
                    </dl>
                </div>
            </template>

            {{-- Extra warning line --}}
            <template x-if="detail">
                <p class="mx-5 mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900 dark:bg-amber-950/60 dark:text-amber-200 sm:mx-7"
                   x-text="detail"></p>
            </template>

            {{-- Actions. Cancel sits first on a phone so a stray tap is harmless. --}}
            <div class="mt-6 flex flex-col-reverse gap-2.5 border-t border-border bg-muted/60 px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
                <button type="button"
                        x-ref="cancel"
                        @click="cancel()"
                        class="w-full rounded-xl border border-input bg-card px-5 py-3 text-base font-semibold text-foreground hover:bg-accent hover:text-accent-foreground sm:w-auto sm:py-2.5 sm:text-sm">
                    Cancel
                </button>

                <button type="button"
                        @click="confirm()"
                        class="w-full rounded-xl px-5 py-3 text-base font-semibold shadow-sm sm:w-auto sm:py-2.5 sm:text-sm"
                        :class="tone === 'danger'
                            ? 'bg-destructive text-destructive-foreground hover:brightness-110'
                            : 'bg-primary text-primary-foreground hover:brightness-110'"
                        x-text="action">
                </button>
            </div>
        </div>
    </div>
</div>
