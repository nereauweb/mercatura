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

/** Adds a file to the Filament FileUpload (FilePond) found inside `container`. */
export function addToFilePond(container, file) {
    const field = container && container.querySelector('[x-data^="fileUploadFormComponent"]');
    const data = field && window.Alpine ? window.Alpine.$data(field) : null;
    if (!data || !data.pond) throw new Error('no file upload field');
    return data.pond.addFile(file);
}

window.mercaturaReportCapture = { captureSupported, captureScreen, addToFilePond };
