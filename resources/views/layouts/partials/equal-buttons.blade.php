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

    SAME HEIGHT ACROSS A ROW OF CONTROLS
    The same measuring is done for filter rows and form rows: a dropdown, a
    search box and a button sitting side by side are all raised to the height
    of the tallest one, so a row never has one control a few pixels shorter
    than its neighbours.

    Inline JS so it does not depend on the compiled stylesheet.
--}}
<script>
(function () {
    var queued = 0;

    function shown(el) { return el.offsetWidth > 0 && el.offsetHeight > 0; }

    function equalise() {
        queued = 0;
        equaliseControls();
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

    var FIELD = 'select:not([multiple]), input[type=text], input[type=search], input[type=date], input[type=email], input[type=number], input[type=tel], input[type=password], input[type=month], input[type=time]';

    function isButtonLike(el) {
        return (el.tagName === 'BUTTON' || el.tagName === 'A') && /(^|\s)rounded/.test(el.className) && shown(el);
    }

    /* The one control (field or button) a child of a row stands for, or null
       when it holds none, or several, or a multi-line box. */
    function controlOf(child) {
        if (child.matches(FIELD) || isButtonLike(child)) return child;
        if (child.querySelector('textarea')) return null;
        var found = child.querySelectorAll(FIELD);
        if (found.length === 1 && shown(found[0]) && !child.querySelector('button, a.rounded-lg, a.rounded-md, a.rounded-xl')) return found[0];
        if (found.length === 0) {
            var btns = Array.prototype.filter.call(child.querySelectorAll('button, a'), isButtonLike);
            if (btns.length === 1) return btns[0];
        }
        return null;
    }

    function equaliseControls() {
        var rows = [];

        document.querySelectorAll('main ' + FIELD.split(', ').join(', main ') + ', [role=dialog] ' + FIELD.split(', ').join(', [role=dialog] ')).forEach(function (field) {
            if (!shown(field)) return;

            var node = field;
            for (var depth = 0; depth < 3 && node.parentElement; depth++) {
                var parent = node.parentElement;
                var cs = window.getComputedStyle(parent);
                var isRow = (cs.display.indexOf('flex') !== -1 && cs.flexDirection.indexOf('column') !== 0) || cs.display.indexOf('grid') !== -1;

                if (isRow && parent.children.length >= 2) {
                    if (rows.indexOf(parent) === -1) rows.push(parent);
                    return;
                }
                node = parent;
            }
        });

        rows.forEach(function (row) {
            var controls = [];
            Array.prototype.forEach.call(row.children, function (child) {
                var c = controlOf(child);
                if (c) controls.push(c);
            });
            if (controls.length < 2) return;

            /* Start from each control's natural height every time, so a row
               that was measured before the fonts loaded (or before the window
               was resized) is not left at a stale size. */
            controls.forEach(function (c) {
                if (c.getAttribute('data-eq')) { c.style.minHeight = ''; c.removeAttribute('data-eq'); }
            });

            var tallest = 0;
            controls.forEach(function (c) { tallest = Math.max(tallest, c.getBoundingClientRect().height); });
            if (tallest > 90) return;

            controls.forEach(function (c) {
                if (c.getBoundingClientRect().height < tallest - 0.5) {
                    c.style.minHeight = tallest + 'px';
                    c.setAttribute('data-eq', '1');
                }
            });
        });
    }

    var watcher = null;
    var WATCH = { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] };

    function queue() {
        if (!queued) queued = window.requestAnimationFrame(run);
    }

    /* The watcher is paused while this script changes sizes itself, so its
       own writes never trigger another pass. */
    function run() {
        if (watcher) watcher.disconnect();
        try { equalise(); } finally { if (watcher) watcher.observe(document.body, WATCH); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        watcher = new MutationObserver(queue);
        watcher.observe(document.body, WATCH);
        queue();
    });
    window.addEventListener('load', queue);
    window.addEventListener('resize', queue);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(queue);
})();
</script>
