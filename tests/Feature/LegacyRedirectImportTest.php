<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LegacyRedirectImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_csv_map_is_loaded_without_chains(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'redirects');
        file_put_contents($file, implode("\n", [
            'from,to,status',
            '# comment',
            '/vecchio/prodotto-1.html,/prodotti/prodotto-1',
            '/vecchio/categoria/,/categorie/categoria,301',
            '/prodotti/prodotto-1,/prodotti/prodotto-1-nuovo',
            '/senza-destinazione,',
            '/stato-non-valido,/x,999',
            '',
        ]));

        $this->artisan('mercatura:redirects-import', ['file' => $file])
            ->expectsOutputToContain('Redirects written: 3, skipped: 2')
            ->assertSuccessful();

        $product = LegacyRedirect::for('/vecchio/prodotto-1.html');
        $category = LegacyRedirect::for('/vecchio/categoria');
        $this->assertNotNull($product);
        $this->assertNotNull($category);
        $this->assertSame('/prodotti/prodotto-1-nuovo', $product->to_path, 'no chain: the first redirect follows the second');
        $this->assertSame('/categorie/categoria', $category->to_path);
        $this->assertSame(301, $category->status_code);
        $this->assertNull(LegacyRedirect::for('/senza-destinazione'));
        $this->assertNull(LegacyRedirect::for('/stato-non-valido'));

        $this->get('/vecchio/prodotto-1.html')->assertRedirect('/prodotti/prodotto-1-nuovo');
        unlink($file);
    }

    public function test_a_missing_file_fails(): void
    {
        $this->artisan('mercatura:redirects-import', ['file' => '/nonexistent.csv'])->assertFailed();
    }

    public function test_bulk_mode_loads_a_large_map_with_the_keep_query_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'map').'.csv';
        $lines = ['from,to,status,keep_query'];
        for ($i = 1; $i <= 2500; $i++) {
            $lines[] = "/old-{$i}.html,/prodotti/new-{$i},301,0";
        }
        $lines[] = '/products,/prodotti,301,1';
        $lines[] = '/gone.html,,410,0';
        file_put_contents($path, implode("\n", $lines));

        $this->artisan('mercatura:redirects-import', ['file' => $path, '--bulk' => true])->expectsOutputToContain('Redirects written: 2502')->assertSuccessful();
        $this->assertSame('/prodotti/new-2500', \App\Models\LegacyRedirect::for('/old-2500.html')?->to_path);
        $this->assertTrue(\App\Models\LegacyRedirect::for('/products')?->keep_query);
        $this->assertSame(410, \App\Models\LegacyRedirect::for('/gone.html')?->status_code);

        // A second load replaces rows in place.
        file_put_contents($path, "/old-1.html,/prodotti/changed,301,0\n");
        $this->artisan('mercatura:redirects-import', ['file' => $path, '--bulk' => true])->assertSuccessful();
        $this->assertSame('/prodotti/changed', \App\Models\LegacyRedirect::for('/old-1.html')?->to_path);
        $this->assertSame(2502, \App\Models\LegacyRedirect::query()->where('from_path', 'like', '/old-%')->count() + 2);
        @unlink($path);
    }
}
