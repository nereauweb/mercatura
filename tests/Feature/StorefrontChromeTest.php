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

    public function test_a_navbar_page_sits_in_the_category_bar_by_default(): void
    {
        \App\Models\Page::query()->create(['title' => 'Chi siamo', 'slug' => 'chi-siamo-test', 'active' => 1, 'navbar' => 1, 'position' => 1, 'products' => 0, 'text' => '<p>Storia</p>']);
        \Illuminate\Support\Facades\Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('text-sm font-semibold uppercase text-primary hover:text-accent">Chi siamo</a>', $html);
        $this->assertStringNotContainsString('@mouseenter', $html, 'menu panes open on click');
    }

    /** The shared navigation is computed once per request; the config must be set before the first render. */
    public function test_utility_pages_move_from_the_category_bar_to_the_header(): void
    {
        \App\Models\Page::query()->create(['title' => 'Chi siamo', 'slug' => 'chi-siamo-test', 'active' => 1, 'navbar' => 1, 'position' => 1, 'products' => 0, 'text' => '<p>Storia</p>']);
        config(['brand.utility_pages' => ['chi-siamo-test']]);
        \Illuminate\Support\Facades\Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('text-sm font-semibold uppercase text-primary hover:text-accent">Chi siamo</a>', $html, 'gone from the category bar');
        $this->assertMatchesRegularExpression('#/contenuti/chi-siamo-test" class="hover:underline">Chi siamo</a>\s*<a href="[^"]*/contattaci" class="hover:underline">#', $html, 'in the utility bar, left of Contatti');
    }

    public function test_pages_flagged_topbar_in_the_admin_are_in_the_utility_bar(): void
    {
        config(['brand.utility_pages' => []]);
        \App\Models\Page::query()->create(['title' => 'Chi siamo', 'slug' => 'chi-siamo-flag', 'active' => 1, 'navbar' => 1, 'topbar' => 1, 'position' => 2, 'products' => 0, 'text' => '<p>Storia</p>']);
        \App\Models\Page::query()->create(['title' => 'Servizi', 'slug' => 'servizi-flag', 'active' => 1, 'navbar' => 0, 'topbar' => 1, 'position' => 1, 'products' => 0, 'text' => '<p>x</p>']);
        \App\Models\Page::query()->create(['title' => 'Nascosta', 'slug' => 'nascosta-flag', 'active' => 0, 'navbar' => 0, 'topbar' => 1, 'position' => 0, 'products' => 0, 'text' => '<p>x</p>']);
        \Illuminate\Support\Facades\Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#/contenuti/servizi-flag" class="hover:underline">Servizi</a>\s*<a href="[^"]*/contenuti/chi-siamo-flag" class="hover:underline">Chi siamo</a>\s*<a href="[^"]*/contattaci"#', $html, 'by position, left of Contacts');
        $this->assertDoesNotMatchRegularExpression('#/contenuti/chi-siamo-flag" class="text-sm font-semibold#', $html, 'a topbar page leaves the category bar even when navbar is set');
        $this->assertStringNotContainsString('nascosta-flag', $html, 'inactive pages stay out');
    }
}
