{{--
    "Install" button, shown to every user in the top bar.

    Chrome and Edge fire beforeinstallprompt when the site qualifies as an app.
    We hold that event in resources/js/app.js. When it is available the button
    opens the browser's own install prompt. When it is not (iPhone Safari,
    Firefox, a page without https, or the browser has not decided yet) the
    button still shows and opens a short popup that explains how to add the app
    to the home screen for that device.

    The button only hides when the page is already running as an installed app
    or right after the app has been installed.

    Note: this x-data sits inside a double quoted HTML attribute, so use single
    quotes only in the script below.
--}}
<div x-data="{
        available: true,

        init() {
            if (window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true) {
                this.available = false;
            }
        },

        manualSteps() {
            const ua = window.navigator.userAgent || '';
            const isIos = /iPhone|iPad|iPod/i.test(ua)
                || (/Macintosh/i.test(ua) && window.navigator.maxTouchPoints > 1);
            const isAndroid = /Android/i.test(ua);
            const local = ['localhost', '127.0.0.1'].includes(window.location.hostname);

            if (!window.isSecureContext && !local) {
                return 'This site is not on a secure (https) address, so the browser cannot install it as an app. Ask your administrator to open the site using https, then try again.';
            }

            if (isIos) {
                return 'Open this page in Safari. Tap the Share button, then choose Add to Home Screen, then tap Add.';
            }

            if (isAndroid) {
                return 'Tap the three dots menu at the top right of your browser, then choose Install app or Add to Home screen.';
            }

            return 'Open your browser menu and choose Install TANZA-FITS (in Chrome or Edge, look for the install icon at the right end of the address bar).';
        },

        async install() {
            const prompt = window.deferredInstallPrompt;

            if (!prompt) {
                if (typeof window.fitsNotice === 'function') {
                    window.fitsNotice({
                        title: 'Install TANZA-FITS',
                        message: this.manualSteps(),
                    });
                }
                return;
            }

            window.deferredInstallPrompt = null;

            prompt.prompt();
            const choice = await prompt.userChoice;

            if (choice && choice.outcome === 'accepted') {
                this.available = false;
            }
        },
     }"
     @pwa-installed.window="available = false"
     x-show="available"
     x-cloak>

    <button type="button"
            @click="install()"
            class="flex items-center gap-2 rounded-md border border-primary/60 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-accent hover:text-accent-foreground">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
        </svg>
        <span class="hidden lg:inline">Install app</span>
        <span class="lg:hidden">Install</span>
    </button>
</div>
