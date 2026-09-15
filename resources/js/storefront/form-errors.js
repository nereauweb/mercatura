/**
 * Validation error UX: highlight fields declared by <div data-field-error>,
 * scroll to the summary (or the first invalid field), clear the highlight
 * when the visitor edits the field. Runs once per page load.
 */
const INVALID_CLASSES = ['border-danger', 'ring-1', 'ring-danger'];

function findInputByName(name) {
    if (!name) return null;
    const direct = document.querySelector(`[name="${name}"]`);
    if (direct) return direct;
    if (name.includes('.')) {
        const [first, ...rest] = name.split('.');
        return document.querySelector(`[name="${first}${rest.map((p) => `[${p}]`).join('')}"]`);
    }
    return null;
}

function attachClearOnEdit(input) {
    const clear = () => {
        input.classList.remove(...INVALID_CLASSES);
        input.removeAttribute('aria-invalid');
        input.removeEventListener('input', clear);
        input.removeEventListener('change', clear);
    };
    input.addEventListener('input', clear);
    input.addEventListener('change', clear);
}

export function scrollAndFocus(element) {
    if (!element) return;
    try {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (e) {
        element.scrollIntoView();
    }
    setTimeout(() => {
        const type = (element.type || '').toLowerCase();
        if (!['checkbox', 'radio', 'hidden'].includes(type)) {
            try { element.focus({ preventScroll: true }); } catch (e) { element.focus(); }
        }
    }, 400);
}

export function initFormErrors() {
    let firstField = null;
    const seen = new Set();
    document.querySelectorAll('[data-field-error]').forEach((marker) => {
        const input = findInputByName(marker.getAttribute('data-field-error'));
        if (!input || seen.has(input)) return;
        seen.add(input);
        input.classList.add(...INVALID_CLASSES);
        input.setAttribute('aria-invalid', 'true');
        if (marker.id) input.setAttribute('aria-describedby', marker.id);
        firstField ??= input;
        attachClearOnEdit(input);
    });
    const summary = document.querySelector('[data-form-errors-summary]');
    if (summary) {
        scrollAndFocus(summary);
    } else if (firstField) {
        scrollAndFocus(firstField);
    }
}
