<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** With APP_DEBUG off (production, staging) the handler must not turn framework-handled exceptions into 500 pages. */
final class ExceptionRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_a_guest_on_the_admin_is_sent_to_the_login_not_to_a_500_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/area-riservata')->assertRedirect();
    }

    public function test_a_real_error_is_the_branded_500_page_without_details(): void
    {
        Route::get('/_test/boom', function (): never {
            throw new \RuntimeException('boom');
        })->middleware('web');
        $response = $this->get('/_test/boom')->assertStatus(500);
        $this->assertStringContainsString('500', (string) $response->getContent());
        $this->assertStringNotContainsString('boom', (string) $response->getContent(), 'no exception details without debug');
    }
}
