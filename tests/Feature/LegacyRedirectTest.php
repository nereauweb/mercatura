<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use App\Models\Page;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** docs/ARCHITECTURE.md §12: stored redirects, 301 never chained, served only for unknown paths. */
class LegacyRedirectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unknown_path_with_a_stored_redirect_is_redirected_once(): void
    {
        LegacyRedirect::record('/vecchia-pagina.html', '/contenuti/chi-siamo');

        $this->get('/vecchia-pagina.html')->assertStatus(301)->assertRedirect('/contenuti/chi-siamo');
        $this->assertSame(1, LegacyRedirect::for('/vecchia-pagina.html')?->hits);
        $this->get('/pagina-mai-esistita')->assertNotFound();
    }

    public function test_gone_paths_answer_410(): void
    {
        LegacyRedirect::record('/catalogo-2019.pdf', '/', 410);
        $this->get('/catalogo-2019.pdf')->assertStatus(410);
    }

    public function test_recording_never_creates_chains(): void
    {
        LegacyRedirect::record('/a', '/b');
        LegacyRedirect::record('/b', '/c');

        $this->assertSame('/c', LegacyRedirect::for('/a')?->to_path);
        $this->assertSame('/c', LegacyRedirect::for('/b')?->to_path);

        LegacyRedirect::record('/c', '/a');
        $this->assertNull(LegacyRedirect::for('/a'), 'a redirect from the new path is removed');
    }

    public function test_changing_a_slug_records_a_redirect(): void
    {
        $page = Page::query()->create(['title' => 'Storia', 'slug' => 'la-storia', 'active' => 1, 'products' => 0, 'text' => '<p>x</p>']);
        $page->update(['slug' => 'chi-siamo-storia']);

        $this->get('/contenuti/la-storia')->assertStatus(301)->assertRedirect('/contenuti/chi-siamo-storia');
        $this->get('/contenuti/chi-siamo-storia')->assertOk();
    }

    public function test_a_redirect_can_forward_the_query_string_and_fill_placeholders(): void
    {
        \App\Models\LegacyRedirect::record('/products', '/prodotti', 301, true);
        $this->get('/products?brand=Parker&new=1')->assertRedirect('/prodotti?brand=Parker&new=1');
        $this->get('/products')->assertRedirect('/prodotti');

        \App\Models\LegacyRedirect::record('/catalogsearch/result', '/search/{q}', 301, false);
        $this->get('/catalogsearch/result/?q=penne+a+sfera')->assertRedirect('/search/penne%20a%20sfera');

        \App\Models\LegacyRedirect::record('/old-list.html', '/prodotti?sale=1', 301, false);
        // A fixed query in the target is kept, the request's one is dropped.
        $this->get('/old-list.html?x=1')->assertRedirect('/prodotti?sale=1');
        $this->assertSame('/prodotti?sale=1', \App\Models\LegacyRedirect::for('/old-list.html')?->to_path);
    }

    public function test_trailing_slashes_are_redirected_once_and_legacy_paths_go_straight_to_their_target(): void
    {
        // The HTTP test helper strips trailing slashes, so the middleware is exercised directly.
        $run = fn (string $uri) => (new \App\Http\Middleware\NormalizeTrailingSlash)->handle(\Illuminate\Http\Request::create($uri), fn () => response('next', 200));

        $this->assertSame('next', $run('/contattaci')->getContent(), 'no slash: passes through');
        $this->assertSame('next', $run('/')->getContent(), 'the root passes through');
        $this->assertSame(301, $run('/contattaci/')->getStatusCode());
        $this->assertStringEndsWith('/contattaci', $run('/contattaci/')->headers->get('Location'));
        $this->assertStringEndsWith('/contattaci?x=1', $run('/contattaci/?x=1')->headers->get('Location'), 'query kept');

        \App\Models\LegacyRedirect::record('/chi-siamo', '/contenuti/chi-siamo');
        $this->assertStringEndsWith('/contenuti/chi-siamo', $run('/chi-siamo/')->headers->get('Location'), 'straight to the legacy target, no chain');
        $this->assertSame(1, \App\Models\LegacyRedirect::for('/chi-siamo')?->hits);

        \App\Models\LegacyRedirect::record('/catalogsearch/result', '/search/{q}');
        $this->assertStringEndsWith('/search/penne', $run('/catalogsearch/result/?q=penne')->headers->get('Location'));

        \App\Models\LegacyRedirect::record('/vecchio-catalogo', '', 410);
        $this->assertSame(410, $run('/vecchio-catalogo/')->getStatusCode());
        $this->assertStringNotContainsString('Redirect Trailing Slashes', (string) file_get_contents(public_path('.htaccess')), 'Apache no longer redirects before the application');
    }
}
