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

    public function test_recaptcha_loads_on_demand_hides_the_badge_and_shows_googles_notice(): void
    {
        config(['mercatura.providers.captcha' => 'recaptcha', 'googlerecaptchav3.site_key' => 'site-key']);

        $field = Blade::render('<x-frontend::captcha field="contact_id" action="contact" />');
        $this->assertStringContainsString('data-captcha', $field, 'marks the form for the on-demand loader');
        $this->assertStringContainsString('policies.google.com/privacy', $field);

        $script = Blade::render('<x-frontend::captcha-init />');
        $this->assertStringNotContainsString("<script id='gReCaptchaScript'", $script, 'Google is not loaded with the page');
        $this->assertStringContainsString('https://www.google.com/recaptcha/api.js?render=site-key&hl=it&onload=mercaturaCaptchaOnload', $script);
        $this->assertStringContainsString('.grecaptcha-badge{visibility:hidden}', $script);
        $this->assertStringContainsString('function onloadCallback', $script, 'the package still renders the widgets once loaded');
    }

    public function test_reports_have_a_score_threshold(): void
    {
        $this->assertContains('report', array_column((array) config('googlerecaptchav3.setting'), 'action'));
    }

    public function test_storefront_forms_use_the_provider_neutral_data_attributes(): void
    {
        $this->get(route('frontend.auth.login'))
            ->assertOk()
            ->assertSee('data-captcha-field="login_id"', false)
            ->assertDontSee('data-recaptcha-', false);
    }
}
