<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\Captcha;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    public function test_null_driver_renders_nothing_and_accepts_requests(): void
    {
        $this->assertSame('', trim(Blade::render('<x-frontend::captcha field="login_id" action="login" />')));
        $this->assertSame('', trim(Blade::render('<x-frontend::captcha-init />')));

        $validator = Validator::make(['email' => 'a@b.it'], ['email' => ['required'], ...Captcha::rules('login')]);
        $this->assertTrue($validator->passes());
        $this->assertSame('captcha_token', Captcha::field());
    }

    public function test_recaptcha_driver_renders_the_field_and_the_refresh_hook(): void
    {
        config(['mercatura.providers.captcha' => 'recaptcha', 'googlerecaptchav3.site_key' => 'site-key']);

        $field = Blade::render('<x-frontend::captcha field="login_id" action="login" />');
        $this->assertStringContainsString('id="login_id"', $field);
        $this->assertStringContainsString('window.mercaturaCaptcha', Blade::render('<x-frontend::captcha-init />'));
        $this->assertSame('g-recaptcha-response', Captcha::field());
    }

    public function test_storefront_forms_use_the_provider_neutral_data_attributes(): void
    {
        $this->get(route('frontend.auth.login'))
            ->assertOk()
            ->assertSee('data-captcha-field="login_id"', false)
            ->assertDontSee('data-recaptcha-', false);
    }
}
