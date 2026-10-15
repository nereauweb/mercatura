/**
 * Screen capture for reports (docs/06_USER_REPORTS.md §5): one frame of the
 * screen the user shares through the browser's own permission prompt, as a
 * PNG File; every track is stopped right after. Used by the storefront
 * dialog (imported) and by the admin panel (separate Vite entry, through
 * window.mercaturaReportCapture). Not available on mobile browsers.
 */
export function captureSupported() {
    return !!(navigator.mediaDevices && typeof navigator.mediaDevices.getDisplayMedia === 'function');
}

const nextFrame = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

/**
 * @param {() => void} hide  called before the prompt: the report dialog must not be in the picture
 * @param {() => void} show  called afterwards, whatever happened
 * @returns {Promise<File>}  rejects with the browser's error (NotAllowedError when the user refuses)
 */
export async function captureScreen(hide = () => {}, show = () => {}) {
    if (!captureSupported()) throw new Error('unsupported');
    hide();
    await nextFrame();
    let stream = null;
    try {
        stream = await navigator.mediaDevices.getDisplayMedia({
            video: { displaySurface: 'browser' },
            audio: false,
            preferCurrentTab: true,
            selfBrowserSurface: 'include',
            surfaceSwitching: 'exclude',
            monitorTypeSurfaces: 'exclude',
        });
        const video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.srcObject = stream;
        await video.play();
        await nextFrame();
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('empty capture'))), 'image/png'));
        const stamp = new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '');
        return new File([blob], `schermata-${stamp}.png`, { type: 'image/png' });
    } finally {
        if (stream) stream.getTracks().forEach((track) => track.stop());
        show();
    }
}

/**
 * Capture mode: the report dialog is hidden, the page scrolls again and a
 * small bar fixed at the bottom lets the user reach the area of the problem
 * before taking the frame (a report opened from the footer would otherwise
 * capture the footer). The bar's own click starts the browser prompt, so the
 * user gesture the Screen Capture API requires is there.
 *
 * @param {{ hide: () => void, show: () => void, labels: { text: string, capture: string, cancel: string } }} options
 * @returns {Promise<File|null>}  null when the user cancels; rejects like captureScreen()
 */
export function pickAndCapture({ hide = () => {}, show = () => {}, labels }) {
    const html = document.documentElement;
    const body = document.body;
    const saved = { htmlOverflow: html.style.overflow, bodyOverflow: body.style.overflow, bodyLocked: body.classList.contains('overflow-hidden') };
    hide();
    html.style.overflow = '';
    body.style.overflow = '';
    body.classList.remove('overflow-hidden');

    const restore = () => {
        html.style.overflow = saved.htmlOverflow;
        body.style.overflow = saved.bodyOverflow;
        if (saved.bodyLocked) body.classList.add('overflow-hidden');
        show();
    };

    const bar = document.createElement('div');
    bar.setAttribute('role', 'dialog');
    bar.setAttribute('aria-label', labels.capture);
    bar.dataset.reportCaptureBar = '';
    bar.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483000;display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;max-width:calc(100vw - 32px);padding:10px 14px;border-radius:12px;background:#111827;color:#fff;font:14px/1.4 system-ui,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35)';
    const text = document.createElement('span');
    text.textContent = labels.text;
    const button = (label, primary) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.style.cssText = `cursor:pointer;border:0;border-radius:9999px;padding:6px 14px;font:600 14px system-ui,sans-serif;${primary ? 'background:#f97316;color:#fff' : 'background:transparent;color:#fff;text-decoration:underline'}`;
        return b;
    };
    const take = button(labels.capture, true);
    const cancel = button(labels.cancel, false);
    bar.append(text, take, cancel);
    body.append(bar);
    take.focus();

    return new Promise((resolve, reject) => {
        const close = () => {
            bar.remove();
            document.removeEventListener('keydown', onKey, true);
        };
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close();
                restore();
                resolve(null);
            }
        };
        document.addEventListener('keydown', onKey, true);
        cancel.addEventListener('click', () => { close(); restore(); resolve(null); });
        take.addEventListener('click', () => {
            close();
            // The page stays where the user scrolled; the dialog comes back only after the frame.
            captureScreen(() => {}, restore).then(resolve, reject);
        });
    });
}

/** Adds a file to the Filament FileUpload (FilePond) found inside `container`. */
export function addToFilePond(container, file) {
    const field = container && container.querySelector('[x-data^="fileUploadFormComponent"]');
    const data = field && window.Alpine ? window.Alpine.$data(field) : null;
    if (!data || !data.pond) throw new Error('no file upload field');
    return data.pond.addFile(file);
}

window.mercaturaReportCapture = { captureSupported, captureScreen, pickAndCapture, addToFilePond };
