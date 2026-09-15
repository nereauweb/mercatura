/**
 * Before a real submit, ask the captcha driver for a fresh token (v3-style
 * tokens expire after ~2 minutes). Forms opt in with data-captcha-field and
 * data-captcha-action; data-captcha-only-path limits the refresh to one
 * action URL. The driver script (CaptchaProvider::renderScript) defines
 * window.mercaturaCaptcha.refresh(fieldId, action) → Promise; without it
 * (null driver) forms submit normally.
 */
function nativeSubmit(form) {
    HTMLFormElement.prototype.submit.call(form);
}

function shouldRefresh(form) {
    const onlyPath = form.getAttribute('data-captcha-only-path');
    if (!onlyPath) return true;
    const action = form.getAttribute('action');
    if (!action) return false;
    try {
        const pathname = new URL(action, window.location.href).pathname.replace(/\/$/, '') || '/';
        let suffix = onlyPath.replace(/\/$/, '');
        if (!suffix.startsWith('/')) suffix = `/${suffix}`;
        return pathname === suffix || pathname.endsWith(suffix);
    } catch (e) {
        return false;
    }
}

function onSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const fieldId = form.getAttribute('data-captcha-field');
    const action = form.getAttribute('data-captcha-action');
    if (!fieldId || !action || !shouldRefresh(form)) return;
    const driver = window.mercaturaCaptcha;
    if (!driver || typeof driver.refresh !== 'function' || !document.getElementById(fieldId)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    Promise.resolve(driver.refresh(fieldId, action)).then(() => nativeSubmit(form)).catch(() => nativeSubmit(form));
}

export function initCaptchaRefresh() {
    document.querySelectorAll('form[data-captcha-field][data-captcha-action]').forEach((form) => {
        form.addEventListener('submit', onSubmit, true);
    });
}
