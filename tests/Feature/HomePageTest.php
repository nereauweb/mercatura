<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Phase 4(b): home. */
final class HomePageTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        $files = [resource_path('views/frontend/pages/home.blade.php'), resource_path('skins/demo/frontend/pages/home.blade.php')];
        foreach (['home', 'product', 'seo'] as $dir) {
            foreach (Finder::create()->files()->in(resource_path("views/frontend/components/{$dir}"))->name('*.blade.php') as $file) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_home_templates_contain_no_uikit_jquery_or_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\./', $source, basename($file).' still uses jQuery or UIkit');
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('moleskine|parker|fg-|bg-blu|bg-arancione|foglie_verdi'), $source, basename($file).' still carries client content');
        }
    }

    public function test_home_renders_with_one_h1_meta_description_and_canonical(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<meta name="description" content="', $html);
        $this->assertStringContainsString('<link rel="canonical" href="', $html);
        $this->assertStringContainsString('<meta property="og:image" content="', $html);
        $this->assertStringContainsString('<title>Mercatura | Il tuo negozio online</title>', $html);
        $this->assertStringNotContainsString('foglie_verdi_su_sfondo_bianco', $html);
    }

    public function test_home_copy_comes_from_lang_and_brand(): void
    {
        config(['brand.name' => 'Negozio Test', 'brand.tagline' => 'Slogan test']);

        $this->get('/')->assertOk()
            ->assertSee('Negozio Test | Slogan test')
            ->assertSee(__('frontend.home.newsletter_title'));
    }
}
