{{-- Screen capture button of the admin's report (docs/06_USER_REPORTS.md §5): one frame through the
     browser's permission prompt, added to the attachments field of the same modal. The Filament
     modals are hidden for the frame. Needs resources/js/report-capture.js (AdminPanelProvider hook). --}}
<div
    x-data="{
        supported: !! (window.mercaturaReportCapture && window.mercaturaReportCapture.captureSupported()),
        busy: false,
        error: '',
        async take() {
            this.busy = true;
            this.error = '';
            const root = document.documentElement;
            try {
                const file = await window.mercaturaReportCapture.captureScreen(
                    () => root.classList.add('mercatura-capturing'),
                    () => root.classList.remove('mercatura-capturing'),
                );
                window.mercaturaReportCapture.addToFilePond(this.$el.closest('form') || document, file);
            } catch (e) {
                if (! e || e.name !== 'NotAllowedError') this.error = @js(__('admin.message.report.capture_failed'));
            } finally {
                this.busy = false;
            }
        },
    }"
    x-show="supported"
    x-cloak
    class="flex flex-col gap-1"
>
    <div>
        <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-camera" x-on:click="take()" x-bind:disabled="busy">
            {{ __('admin.message.report.capture') }}
        </x-filament::button>
    </div>
    <p class="text-xs text-gray-500">{{ __('admin.message.report.capture_hint') }}</p>
    <p x-show="error" x-text="error" class="text-xs text-danger-600"></p>
</div>
