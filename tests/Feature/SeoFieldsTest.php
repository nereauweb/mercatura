<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Support\CanonicalUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Stored SEO fields override the generated defaults (docs/ARCHITECTURE.md §12). */
class SeoFieldsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_page_renders_generated_defaults_without_overrides(): void
    {
        Page::query()->create(['title' => 'Servizi', 'slug' => 'servizi', 'active' => 1, 'products' => 0, 'text' => '<p>Testo</p>']);

        $this->get('/contenuti/servizi')->assertOk()
            ->assertSee('<link rel="canonical" href="'.CanonicalUrl::absolute('/contenuti/servizi').'" />', false)
            ->assertDontSee('noindex')
            ->assertSee('<meta property="og:title" content="Servizi" />', false);
    }

    public function test_page_renders_stored_overrides_and_noindex(): void
    {
        Page::query()->create([
            'title' => 'Servizi', 'slug' => 'servizi', 'active' => 1, 'products' => 0, 'text' => '<p>Testo</p>',
            'canonical_url' => 'https://example.com/altrove', 'noindex' => true,
            'og_title' => 'Titolo social', 'og_description' => 'Descrizione social', 'og_image' => '/img/og-alt.png',
        ]);

        $this->get('/contenuti/servizi')->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertSee('<meta property="og:url" content="https://example.com/altrove" />', false)
            ->assertSee('<meta property="og:title" content="Titolo social" />', false)
            ->assertSee('<meta property="og:description" content="Descrizione social" />', false)
            ->assertSee(url('/img/og-alt.png'), false);
    }
}
