<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/** Staging switch (config mercatura.staging): crawlers kept out, optional basic auth, webhooks reachable. */
final class StagingTest extends TestCase
{
    public function test_a_live_instance_serves_the_core_robots_and_no_staging_header(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('Disallow: /carrello/', (string) $robots->getContent());
        $this->assertStringContainsString('Sitemap: ', (string) $robots->getContent());
        $this->assertStringNotContainsString("Disallow: /\n", (string) $robots->getContent());
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'a static file would bypass the switch');
        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_staging_keeps_crawlers_out(): void
    {
        config(['mercatura.staging.enabled' => true]);
        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->assertOk()->getContent());
        $home = $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', (string) $home->getContent());
    }

    public function test_staging_basic_auth_protects_everything_but_the_excepted_paths(): void
    {
        config(['mercatura.staging.enabled' => true, 'mercatura.staging.user' => 'gesca', 'mercatura.staging.password' => 'segreto']);
        $this->get('/')->assertStatus(401)->assertHeader('WWW-Authenticate');
        $this->get('/', ['Authorization' => 'Basic '.base64_encode('gesca:sbagliata')])->assertStatus(401);
        $this->get('/', ['Authorization' => 'Basic '.base64_encode('gesca:segreto')])->assertOk();
        $this->assertNotSame(401, $this->post('/stripe/webhook')->getStatusCode(), 'webhooks are not behind the staging password');
        $this->get('/admin/login', ['Authorization' => 'Basic '.base64_encode('gesca:segreto')])->assertOk();
    }
}
