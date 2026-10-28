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
        notice: false,
        list: [],

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
            this.notice  = false;
            this.list    = [];

            // Guarded so a second request while one is already showing does
            // not lock twice and leave the page stuck after it closes.
            if (! this.open) {
                this.open = true;
                this.lock();
            }

            this.$nextTick(() => this.$refs.cancel && this.$refs.cancel.focus());
        },

        /*
         | A message with one OK button (no question asked). Used for the
         | success messages the server sends back and for form errors:
         |   fitsNotice({ title, message, tone: 'success'|'danger'|'default', list: [] })
         */
        showNotice(d) {
            this.title   = d.title || '';
            this.message = d.message || '';
            this.detail  = '';
            this.action  = d.action || 'OK';
            this.tone    = d.tone || 'default';
            this.review  = [];
            this.list    = d.list || [];
            this.proceed = null;
            this.viewer  = null;
            this.notice  = true;

            if (! this.open) {
                this.open = true;
                this.lock();
            }

            this.$nextTick(() => this.$refs.ok && this.$refs.ok.focus());
        },

        /* The ring around the big icon. Inline colours, so they never depend
           on the compiled stylesheet being up to date. */
        ringStyle() {
            const c = this.tone === 'danger'  ? ['#fca5a5', '#dc2626']
                    : this.tone === 'success' ? ['#86efac', '#16a34a']
                    :                           ['#bfdbfe', '#2563eb'];

            return 'width:5rem;height:5rem;border:4px solid ' + c[0] + ';color:' + c[1] + ';';
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
     @notice-request.window="showNotice($event.detail)"
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

            {{-- Heading, in the SweetAlert style: a big icon in a ring, then a
                 centred title and message. --}}
            <div class="px-6 pt-8 text-center sm:px-8" style="text-align:center;">
                <span class="mx-auto flex items-center justify-center rounded-full"
                      :style="ringStyle()" aria-hidden="true">
                    {{-- One icon per tone. Separate svgs rather than a template
                         inside an svg, which browsers do not parse as expected. --}}
                    <svg x-show="tone === 'success'" class="h-10 w-10" fill="none" stroke="currentColor"
                         stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    <svg x-show="tone === 'danger'" class="h-10 w-10" fill="none" stroke="currentColor"
                         stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 6v8m0 4h.01"/>
                    </svg>
                    <svg x-show="tone !== 'success' && tone !== 'danger'" class="h-10 w-10" fill="none" stroke="currentColor"
                         stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M9.1 9a3 3 0 015.8 1c0 2-2.9 2.5-2.9 4.5M12 18.5h.01"/>
                    </svg>
                </span>

                <h2 class="mt-5 text-2xl font-bold leading-snug text-foreground" x-text="title"></h2>
                <p x-show="message" class="mt-2 text-base leading-relaxed text-muted-foreground" x-text="message"></p>

                {{-- Several lines, for form errors --}}
                <ul x-show="list.length"
                    class="mx-auto mt-4 max-h-[40vh] list-inside list-disc space-y-1 overflow-y-auto rounded-xl bg-muted px-4 py-3 text-left text-base text-foreground">
                    <template x-for="line in list" :key="line"><li x-text="line"></li></template>
                </ul>
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

            {{-- Actions: centred, side by side from a tablet up. Cancel sits
                 first on a phone so a stray tap is harmless. --}}
            <div class="mt-7 flex flex-col-reverse items-stretch justify-center gap-3 px-6 pb-8 sm:flex-row sm:px-8">
                <button type="button"
                        x-ref="cancel"
                        x-show="! notice"
                        @click="cancel()"
                        class="w-full rounded-lg px-7 py-3 text-base font-semibold text-white hover:brightness-110 sm:w-auto sm:min-w-[8.5rem]"
                        style="background-color:#64748b;">
                    Cancel
                </button>

                <button type="button"
                        x-ref="ok"
                        @click="confirm()"
                        class="w-full rounded-lg px-7 py-3 text-base font-semibold text-white shadow-sm hover:brightness-110 sm:w-auto sm:min-w-[8.5rem]"
                        :style="'background-color:' + (tone === 'danger' ? '#dc2626' : '#15803d') + ';'"
                        x-text="action">
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Lets any script show a one-button popup: fitsNotice({ title, message, tone }) --}}
<script>
    window.fitsNotice = function (options) {
        window.dispatchEvent(new CustomEvent('notice-request', { detail: options }));
    };
</script>

{{-- The server's own messages, shown as a popup instead of a banner. A form
     page opts in to showing its validation errors this way with
     @section('popupErrors', true). --}}
@php
    $noticePayload = null;

    if (View::hasSection('popupErrors') && $errors->any()) {
        $noticePayload = [
            'title' => 'Please correct the following',
            'tone'  => 'danger',
            'list'  => array_values(array_unique($errors->all())),
        ];
    } elseif (session('status')) {
        $noticePayload = ['title' => session('status'), 'tone' => 'success'];
    }
@endphp
@if ($noticePayload)
    <script>
        (function () {
            var payload = @json($noticePayload);
            function go() { setTimeout(function () { window.fitsNotice(payload); }, 350); }
            if (document.readyState === 'complete') { go(); } else { window.addEventListener('load', go); }
        })();
    </script>
@endif
