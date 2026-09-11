import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

/*
 |-----------------------------------------------------------------------------
 | Global confirmation
 |-----------------------------------------------------------------------------
 | Section 91 of the system requirements: no important action is finalised the
 | moment a button is pressed. Most of the people using this are farmers, many
 | of them elderly, so every save, approve, reject, archive, allocate, verify
 | and send must first show what is about to happen and ask them to confirm.
 |
 | Rather than hand building a modal into every form, any form or link opts in
 | by carrying a data-confirm attribute. This listener catches the submit or
 | click before it reaches the server and hands it to the one dialog that lives
 | in the layout.
 |
 |   <form method="POST" action="..."
 |         data-confirm="Are you sure you want to approve this registration?"
 |         data-confirm-title="Approve farmer registration"
 |         data-confirm-action="Confirm Approval"
 |         data-confirm-tone="default"
 |         data-confirm-review='[{"label":"Farmer","value":"Juan Dela Cruz"}]'>
 |
 | Nothing is sent until the person presses the confirm button in that dialog.
 */

/*
 | Builds the "Please review" list straight out of a form's own fields, so a
 | plain create or edit form gets a real review step without anyone having to
 | write the summary twice. Turn it on with data-confirm-review="auto".
 |
 | A field is labelled by, in order: its data-confirm-label, the <label for=...>
 | pointing at it, or the label it sits inside. A field with
 | data-confirm-skip is left out. Empty fields are left out too, so the list
 | stays short enough to actually be read.
 */
function cleanLabel(text) {
    return text.trim()
        .replace(/\s+/g, ' ')
        .replace(/[*:]\s*$/, '')
        .trim();
}

function labelFor(field, form) {
    if (field.dataset.confirmLabel) {
        return field.dataset.confirmLabel;
    }

    if (field.id && form) {
        const tag = form.querySelector('label[for="' + CSS.escape(field.id) + '"]');

        if (tag) {
            return cleanLabel(tag.textContent);
        }
    }

    const wrapping = field.closest('label');

    if (wrapping) {
        return cleanLabel(wrapping.textContent);
    }

    // These forms are written as <div><label>Name *</label><input></div>, so
    // look for a label in the field's own wrapper. The single field check keeps
    // a grid heading from being pinned onto the wrong input.
    let node = field.parentElement;

    for (let depth = 0; node && depth < 2; depth += 1, node = node.parentElement) {
        const tag = node.querySelector('label');

        if (tag && node.querySelectorAll('input, select, textarea').length === 1) {
            return cleanLabel(tag.textContent);
        }
    }

    return null;
}

function displayValue(field) {
    if (field.type === 'password') {
        return field.value ? '••••••••' : '';
    }

    if (field.type === 'file') {
        return field.files && field.files.length
            ? Array.from(field.files).map((file) => file.name).join(', ')
            : '';
    }

    if (field.type === 'checkbox') {
        return field.checked ? 'Yes' : 'No';
    }

    if (field.type === 'radio') {
        return field.checked ? (labelFor(field, field.form) || field.value) : '';
    }

    if (field.tagName === 'SELECT') {
        const chosen = field.options[field.selectedIndex];

        return chosen && chosen.value !== '' ? chosen.text.trim() : '';
    }

    return field.value.trim();
}

function reviewFromForm(form) {
    const rows = [];
    const seen = new Set();

    form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return;
        }

        if (field.disabled || field.dataset.confirmSkip !== undefined) {
            return;
        }

        const label = labelFor(field, form);
        const value = displayValue(field);

        if (!label || value === '' || seen.has(label)) {
            return;
        }

        seen.add(label);
        rows.push({ label, value });
    });

    return rows;
}

function readConfirmConfig(element) {
    let review = [];

    if (element.dataset.confirmReview === 'auto' && element instanceof HTMLFormElement) {
        review = reviewFromForm(element);
    } else if (element.dataset.confirmReview) {
        try {
            review = JSON.parse(element.dataset.confirmReview);
        } catch (error) {
            review = [];
        }
    }

    return {
        title:   element.dataset.confirmTitle  || 'Please confirm',
        message: element.dataset.confirm       || 'Are you sure you want to continue?',
        detail:  element.dataset.confirmDetail || '',
        action:  element.dataset.confirmAction || 'Confirm',
        tone:    element.dataset.confirmTone   || 'default',
        review,
    };
}

function askToConfirm(config, proceed) {
    window.dispatchEvent(new CustomEvent('confirm-request', {
        detail: { ...config, proceed },
    }));
}

// Forms. The submitter is kept so a named button such as
// <button name="decision" value="approve"> still posts its value.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
        return;
    }

    if (form.dataset.confirmed === 'yes') {
        form.dataset.confirmed = '';

        return;
    }

    event.preventDefault();

    const submitter = event.submitter;

    askToConfirm(readConfirmConfig(form), () => {
        form.dataset.confirmed = 'yes';

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
        } else {
            form.submit();
        }
    });
}, true);

// Links that lead somewhere consequential.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-confirm]');

    if (!link) {
        return;
    }

    event.preventDefault();

    askToConfirm(readConfirmConfig(link), () => {
        window.location.href = link.href;
    });
});

/*
 |-----------------------------------------------------------------------------
 | Installing the system on a phone
 |-----------------------------------------------------------------------------
 | The system is one responsive web application, but Android and desktop Chrome
 | will offer to add it to the home screen. The browser fires this event when it
 | decides the app qualifies. It is kept so the interface can show its own
 | "Install app" button instead of relying on the browser menu, which farmers are
 | unlikely to find on their own.
 */
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

window.addEventListener('appinstalled', () => {
    window.deferredInstallPrompt = null;
    window.dispatchEvent(new CustomEvent('pwa-installed'));
});

/*
 |-----------------------------------------------------------------------------
 | Service worker
 |-----------------------------------------------------------------------------
 | Registered only where the browser allows it: a secure origin, or localhost
 | during development. It gives the app its home screen behaviour and a readable
 | offline page instead of the browser error page.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Registration failing must never break the page. The system works
            // exactly the same without it, just without offline handling.
        });
    });
}
