{{--
    EQUAL-SIZE BUTTON PAIRS (Oct 2026)

    Wherever a row of buttons has a Cancel in it (the confirmation popups,
    every modal footer, form action bars), the buttons beside it are made
    the same width and the same height. They are written by hand in some forty
    places, each with its own padding, so "Cancel" and "Confirm & Submit" came
    out in two different sizes.

    Rather than touch every one of those files, this measures a row once it is
    on screen and raises the smaller buttons to the size of the largest. It
    only ever grows a button, so once a row is equal the script writes nothing
    and stops. Rows that stack in a column (phones) are left alone, because
    their buttons are already full width.

    Inline JS so it does not depend on the compiled stylesheet.
--}}
<script>
(function () {
    var queued = 0;

    function shown(el) { return el.offsetWidth > 0 && el.offsetHeight > 0; }

    function equalise() {
        queued = 0;
        var done = [];

        document.querySelectorAll('button, a').forEach(function (el) {
            if ((el.textContent || '').trim().toLowerCase() !== 'cancel') return;

            var row = el.parentElement;
            if (!row || done.indexOf(row) !== -1) return;
            done.push(row);

            var cs = window.getComputedStyle(row);
            if (cs.display.indexOf('flex') !== -1 && cs.flexDirection.indexOf('column') === 0) return;

            var kids = Array.prototype.filter.call(row.children, function (c) {
                return (c.tagName === 'BUTTON' || c.tagName === 'A') && shown(c);
            });
            if (kids.length < 2) return;

            var w = 0, h = 0;
            kids.forEach(function (k) { w = Math.max(w, k.offsetWidth); h = Math.max(h, k.offsetHeight); });

            kids.forEach(function (k) {
                if (k.offsetWidth < w - 1) k.style.minWidth = w + 'px';
                if (k.offsetHeight < h - 1) k.style.minHeight = h + 'px';
                if (k.style.minWidth || k.style.minHeight) {
                    k.style.textAlign = 'center';
                    if (window.getComputedStyle(k).display.indexOf('flex') !== -1) {
                        k.style.justifyContent = 'center';
                        k.style.alignItems = 'center';
                    }
                }
            });
        });
    }

    function queue() {
        if (!queued) queued = window.requestAnimationFrame(equalise);
    }

    document.addEventListener('DOMContentLoaded', function () {
        queue();
        new MutationObserver(queue).observe(document.body, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['style', 'class', 'hidden']
        });
    });
    window.addEventListener('load', queue);
})();
</script>
