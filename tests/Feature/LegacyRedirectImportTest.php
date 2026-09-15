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
}
