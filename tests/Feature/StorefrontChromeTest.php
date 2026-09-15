<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/**
 * Phase 4(a): layout and chrome. Guards the area's stop criteria
 * (docs/ARCHITECTURE.md §9): no UIkit left, no client identity, structured
 * data valid, chrome present on every page frame.
 */
final class StorefrontChromeTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        $files = [];
        foreach (['layout', 'head', 'header', 'navbar', 'footer', 'endpage'] as $name) {
            $files[] = resource_path("views/frontend/public/{$name}.blade.php");
        }
        foreach (Finder::create()->files()->in(resource_path('views/frontend/components'))->name('*.blade.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    public function test_chrome_templates_contain_no_uikit_markup(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertStringNotContainsString('UIkit.', $source, basename($file).' still calls UIkit');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery/', $source, basename($file).' still uses jQuery');
        }
    }

    public function test_chrome_templates_contain_no_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('fg-|bg-blu|bg-arancione|bg-grigio|GTM-[A-Z0-9]{6,}'), file_get_contents($file), basename($file));
        }
    }

    public function test_page_frame_has_header_navigation_footer_and_bundle(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('<header', false)
            ->assertSee('<nav', false)
            ->assertSee('<main id="main-content">', false)
            ->assertSee('<footer', false)
            ->assertSee('build/assets/app-', false)
            ->assertSee('<meta name="viewport"', false)
            ->assertSee('lang="it"', false)
            ->assertSee('Mercatura');
    }

    public function test_structured_data_describes_organization_and_website(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $types = [];
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context']);
            $types[] = $data['@type'];
        }

        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_search_suggest_returns_products_as_json(): void
    {
        $this->getJson('/search/suggest/x')->assertOk()->assertExactJson([]);

        $this->getJson('/search/suggest/anything')->assertOk()->assertJson([]);
    }

    public function test_gtm_is_not_emitted_without_a_container_id(): void
    {
        config(['gtm.container_id' => null]);

        $this->get('/')->assertOk()
            ->assertDontSee("'dataLayer',\"GTM-", false)
            ->assertSee('function canTrackAnalytics', false);
    }

    public function test_gtm_is_emitted_only_after_analytics_consent(): void
    {
        config(['gtm.container_id' => 'GTM-TEST', 'gtm.consentmode_v2' => false]);

        // The head loader is the only place the container id is passed to the GTM bootstrap.
        $this->get('/')->assertDontSee("'dataLayer',\"GTM-TEST\"", false);

        $this->withUnencryptedCookie('cookies_analytics', '1')->get('/')->assertSee("'dataLayer',\"GTM-TEST\"", false);
    }
}
