{{-- @mercatura-view frontend.components.flash @version 1 --}}
{{-- Session flash messages as toasts, bottom-centre, auto-dismissed. Overlay: never pushes layout. --}}
@php
    $messages = [];
    if (request()->hasSession()) {
        foreach (['status' => 'info', 'success' => 'success', 'error' => 'danger'] as $key => $level) {
            if (session($key)) {
                $messages[] = ['level' => $level, 'text' => (string) session($key)];
            }
        }
    }
@endphp
@if($messages !== [])
<div x-data="flashMessages" data-messages='@json($messages)' class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
    <template x-for="(message, index) in messages" :key="index">
        <div x-show="!message.hidden" x-transition.opacity class="mercatura-flash flex items-center gap-3 text-on-primary"
             :class="{ 'bg-primary': message.level === 'info', 'bg-positive': message.level === 'success', 'bg-danger': message.level === 'danger' }">
            <span x-text="message.text"></span>
            <button type="button" class="rounded p-0.5 hover:bg-white/20" @click="dismiss(index)" aria-label="{{ __('frontend.flash.dismiss') }}"><x-frontend::icon name="close" class="h-4 w-4" /></button>
        </div>
    </template>
</div>
@endif
