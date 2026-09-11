{{--
    Sets the theme BEFORE the page paints.

    This has to be an inline script in the head, ahead of the stylesheet. If
    the class were added later, by Alpine or at the end of the body, a person
    who chose dark mode would get a white flash on every page load.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var dark = stored
                ? stored === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;

            document.documentElement.classList.toggle('dark', dark);
        } catch (error) {
            // Private browsing can refuse localStorage. Light mode is the
            // sensible fallback and nothing else on the page depends on this.
        }
    })();
</script>
