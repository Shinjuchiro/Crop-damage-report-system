{{--
    "Install app" button.

    Chrome and Edge fire beforeinstallprompt when the site qualifies as an app.
    We hold that event in resources/js/app.js and show this button instead,
    because a farmer is not going to find "Add to Home screen" buried in the
    browser menu on their own.

    The button hides itself when the browser has not offered installation, when
    the app is already installed, or when the page is already running as an
    installed app.
--}}
<div x-data="{
        available: false,

        init() {
            if (window.deferredInstallPrompt) {
                this.available = true;
            }

            if (window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true) {
                this.available = false;
            }
        },

        async install() {
            const prompt = window.deferredInstallPrompt;

            if (!prompt) {
                return;
            }

            this.available = false;
            window.deferredInstallPrompt = null;

            prompt.prompt();
            await prompt.userChoice;
        },
     }"
     @pwa-installable.window="available = true"
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
        <span class="hidden sm:inline">Install app</span>
        <span class="sm:hidden">Install</span>
    </button>
</div>
