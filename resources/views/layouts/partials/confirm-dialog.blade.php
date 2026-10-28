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
        viewer: null,

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
            this.viewer  = null;

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
            this.viewer  = null;
            this.unlock();
        },

        confirm() {
            if (! this.open) {
                return;
            }

            const run = this.proceed;

            this.open    = false;
            this.proceed = null;
            this.viewer  = null;
            this.unlock();

            if (run) {
                run();
            }
        },
     }"
     @confirm-request.window="show($event.detail)"
     @keydown.escape.window="open && (viewer ? viewer = null : cancel())"
     x-cloak>

    {{--
        z-[100] puts this above everything the app itself draws: the sidebar
        is z-40, the drawer backdrop z-30, the top bar z-20 and the phone
        bottom bar z-30. Leaflet is handled separately, in app.css, by giving
        the map container its own stacking context so its internal z-index of
        400 stops competing with the rest of the page.
    --}}
    <div x-show="open"
         style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:100;"
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
             style="position:absolute;top:0;right:0;bottom:0;left:0;background-color:rgba(15,23,42,0.6);"
             class="absolute inset-0 bg-slate-900/60"
             aria-hidden="true"></div>

        {{-- relative, so the panel sits above the dim rather than under it --}}
        <div x-show="open"
             x-transition.opacity.duration.150ms
             class="relative w-full max-w-lg overflow-hidden rounded-t-2xl bg-card text-card-foreground shadow-2xl sm:rounded-2xl">

            {{-- One photo, full size, over the review. Back returns to the
                 review exactly as it was. --}}
            <div x-show="viewer" x-cloak
                 class="absolute inset-0 z-10 flex flex-col bg-card">
                <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 sm:px-7">
                    <p class="min-w-0 truncate text-sm font-semibold text-foreground" x-text="viewer ? viewer.label : ''"></p>
                    <button type="button" @click="viewer = null"
                            class="shrink-0 rounded-xl border border-input bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-accent hover:text-accent-foreground">
                        Back to review
                    </button>
                </div>
                <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-muted p-3">
                    <img :src="viewer ? viewer.url : ''" :alt="viewer ? viewer.label : ''"
                         class="max-h-[70vh] w-auto max-w-full rounded-lg object-contain">
                </div>
            </div>

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
                                <dd class="text-sm font-semibold text-foreground sm:text-right">
                                    <span x-text="row.value"></span>

                                    {{-- Photos and files in this row stay openable, so
                                         the person can look at exactly what they are
                                         about to send before they confirm. An image
                                         opens here, over the review; any other file
                                         opens in its own tab. --}}
                                    <span x-show="row.links && row.links.length" x-cloak
                                          class="mt-2 flex flex-wrap gap-2 sm:justify-end">
                                        <template x-for="(link, i) in (row.links || [])" :key="link.url">
                                            <span>
                                                <button type="button" x-show="link.image !== false"
                                                        @click="viewer = link"
                                                        :aria-label="'View ' + link.label"
                                                        class="block h-14 w-14 overflow-hidden rounded-lg border border-border bg-card
                                                               ring-primary hover:ring-2 focus:outline-none focus:ring-2">
                                                    <img :src="link.url" :alt="link.label" class="h-full w-full object-cover">
                                                </button>
                                                <a x-show="link.image === false" :href="link.url" target="_blank" rel="noopener"
                                                   class="inline-flex max-w-[12rem] items-center truncate rounded-lg border border-border
                                                          bg-card px-3 py-2 text-xs font-semibold text-primary underline hover:bg-accent"
                                                   x-text="link.label"></a>
                                            </span>
                                        </template>
                                    </span>
                                </dd>
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
