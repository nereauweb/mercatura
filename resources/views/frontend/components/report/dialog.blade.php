{{-- @mercatura-view frontend.components.report.dialog @version 2 --}}
{{-- Report dialog (docs/06_USER_REPORTS.md R4/R5), mounted once by the layout: a report of the
     current page to the admin, or, for an admin, straight to the helpdesk (§2.9). Rendered only
     for who may report (ReportAccess::offers); opened by the `report-open` window event, see
     frontend.components.report.trigger. Overlays the page: nothing moves when it opens. --}}
@php
    $access = app(\App\Support\Reports\ReportAccess::class);
    $user = auth()->user();
    $user = $user instanceof \App\Models\User ? $user : null;
    $r = 'frontend.reports.';
@endphp
@if($access->offers($user))
@php
    if (! isset($errors)) { view()->share('errors', new \Illuminate\Support\ViewErrorBag); $errors = view()->shared('errors'); }
    $direct = $access->forwardsDirectly($user);
    $target = $direct ? app(\App\Contracts\MessageForwarder::class)->label() : '';
    $canAttach = $direct || $access->canAttach($user);
    $config = [
        'csrf' => csrf_token(),
        'endpoint' => route('frontend.reports.store'),
        'maxFiles' => (int) config('mercatura.reports.max_files'),
        'captchaField' => 'report_captcha',
        'captchaAction' => 'report',
        'labels' => ['error' => __($r.'error'), 'tooManyFiles' => __($r.'too_many_files', ['max' => (int) config('mercatura.reports.max_files')])],
    ];
    $kinds = collect(\App\Enums\ReportKind::cases())->mapWithKeys(fn ($k) => [$k->value => __($r.'kinds.'.$k->value)])->all();
@endphp
<div x-data="reportDialog(@js($config))" @report-open.window="show()" @keydown.escape.window="open && close()">
    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 md:p-8">
        <div role="dialog" aria-modal="true" aria-labelledby="report-dialog-title" @click.outside="close()" class="w-full max-w-xl rounded-card bg-surface shadow-xl">
            <header class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 id="report-dialog-title" class="text-lg font-bold text-primary">{{ __($r.'title') }}</h2>
                <button type="button" @click="close()" class="rounded p-1 text-text-muted hover:text-danger" aria-label="{{ __($r.'close') }}"><x-frontend::icon name="close" /></button>
            </header>

            <div x-cloak x-show="sent" class="p-6 text-center">
                <p class="text-xl font-bold text-primary">{{ __($r.'sent_title') }}</p>
                <p class="mt-2" x-text="sentText">{{ __($r.'sent_text') }}</p>
                <button type="button" @click="close()" class="mt-6 rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong">{{ __($r.'close') }}</button>
            </div>

            <form x-show="! sent" x-ref="form" @submit.prevent="send()" class="grid gap-3 p-4 md:p-6" enctype="multipart/form-data" novalidate>
                <p class="text-sm text-text-muted">{{ $direct ? __($r.'intro_admin', ['target' => $target]) : __($r.'intro') }}</p>
                @if($user === null)
                <div class="hidden" aria-hidden="true"><label for="report-website">Website</label><input type="text" name="website" id="report-website" tabindex="-1" autocomplete="off" value=""></div>
                <x-frontend::forms.input name="name" id="report-name" :label="__('frontend.forms.name')" required autocomplete="name" />
                <x-frontend::forms.input name="email" id="report-email" type="email" :label="__('frontend.forms.email')" required autocomplete="email" />
                @endif
                <x-frontend::forms.select name="kind" id="report-kind" :label="__($r.'kind')" :options="$kinds" :value="\App\Enums\ReportKind::Bug->value" required />
                <x-frontend::forms.input name="title" id="report-title" :label="__($r.'subject')" required maxlength="{{ (int) config('mercatura.reports.title_max') }}" x-ref="title" />
                <div>
                    <label for="report-description" class="mb-1 block text-sm font-semibold">{{ __($r.'description') }} <em class="text-danger not-italic">*</em></label>
                    <textarea name="description" id="report-description" rows="5" required maxlength="{{ (int) config('mercatura.reports.description_max') }}" placeholder="{{ __($r.'description_placeholder') }}" class="w-full rounded border border-border bg-surface px-3 py-2 text-sm focus:border-accent focus:outline-none"></textarea>
                </div>
                @if($canAttach)
                <div>
                    <label for="report-attachments" class="mb-1 block text-sm font-semibold">{{ __($r.'attachments') }}</label>
                    <input type="file" name="attachments[]" id="report-attachments" multiple accept="{{ implode(',', \App\Support\Reports\ReportAttachments::MIMETYPES) }}" @change="pick($event)" class="block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-semibold">
                    <p class="mt-1 text-xs text-text-muted">{{ __($r.'attachments_hint', ['max' => (int) config('mercatura.reports.max_files'), 'mb' => (int) round(config('mercatura.reports.max_file_kb') / 1024)]) }}</p>
                    <ul x-cloak x-show="files.length" class="mt-1 text-xs text-text-muted">
                        <template x-for="file in files" :key="file.name"><li x-text="`${file.name} (${file.size} KB)`"></li></template>
                    </ul>
                </div>
                @endif
                @unless($direct)
                <x-frontend::captcha field="report_captcha" action="report" />
                <p class="text-xs text-text-muted">{{ __($r.'privacy') }}</p>
                @else
                <p class="text-xs text-text-muted">{{ __($r.'admin_hint') }}</p>
                @endunless
                <ul x-cloak x-show="errors.length" class="rounded-card bg-danger/10 p-3 text-sm text-danger" role="alert">
                    <template x-for="error in errors"><li x-text="error"></li></template>
                </ul>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()" class="rounded-full border border-border px-5 py-2 text-sm font-semibold">{{ __($r.'cancel') }}</button>
                    <button type="submit" :disabled="sending" class="rounded-full bg-accent px-6 py-2 text-sm font-bold uppercase text-on-accent hover:bg-accent-strong disabled:opacity-60">
                        <span x-show="! sending">{{ __($r.'send') }}</span><span x-cloak x-show="sending">{{ __($r.'sending') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@once
@push('scripts')
<x-frontend::captcha-init />
@endpush
@endonce
@endif
