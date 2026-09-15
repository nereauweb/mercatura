{{-- @mercatura-view frontend.components.captcha @version 2 --}}
{{-- Captcha field for a form, rendered by the configured CaptchaProvider (mercatura.providers.captcha). Renders nothing with the null driver. --}}
@props(['field', 'action'])
@php $captcha = app(\App\Contracts\CaptchaProvider::class); @endphp
@if($captcha->enabled())
<div {{ $attributes }}>
    {!! $captcha->renderField($field, $action) !!}
    @error($captcha->responseField())<p class="mt-1 text-xs text-danger" data-field-error="{{ $captcha->responseField() }}">{{ $message }}</p>@enderror
</div>
@endif
