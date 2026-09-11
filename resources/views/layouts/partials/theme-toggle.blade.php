{{--
    Light and dark switch for the top bar.

    The choice is kept in this browser only, which is what people expect: the
    MAO staff member on a bright office monitor and the technician checking
    reports at night are the same account with different needs.
--}}
<button x-data="{
            dark: document.documentElement.classList.contains('dark'),

            toggle() {
                this.dark = ! this.dark;
                document.documentElement.classList.toggle('dark', this.dark);

                try {
                    localStorage.setItem('theme', this.dark ? 'dark' : 'light');
                } catch (error) {
                    // Nothing to do. The page still looks right for this visit.
                }
            },
        }"
        @click="toggle()"
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground
               transition-colors hover:bg-accent hover:text-accent-foreground"
        :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">

    {{-- Sun, shown while in dark mode as the way back out --}}
    <svg x-show="dark" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>

    {{-- Moon --}}
    <svg x-show="! dark" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
    </svg>
</button>
