<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\CanonicalUrl;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:GenerateSitemap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sitemap';

    /**
     * Routes to exclude from sitemap
     *
     * @var array
     */
    protected $excludedRoutes = ['carrello', 'checkout', 'ordine', 'accedi', 'logout'];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $websiteUrl = CanonicalUrl::absolute('/');
        $sitemapPath = public_path('sitemap.xml');
        $this->line("Generazione sitemap in: $sitemapPath per website: $websiteUrl");

        $sitemap = Sitemap::create();

        // Add homepage — lastmod basato sull'ultimo prodotto aggiornato
        $lastProductUpdate = Product::where('active', 1)->max('updated_at');

        $sitemap->add(Url::create($websiteUrl)
            ->setPriority(1.0)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setLastModificationDate(Carbon::parse($lastProductUpdate ?? now())));

        // Add static public pages
        $sitemap->add(Url::create(CanonicalUrl::absolute('/contattaci'))
            ->setPriority(0.8)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
            ->setLastModificationDate(
                Carbon::parse(Page::where('slug', 'contattaci')->value('updated_at') ?? now())
            ));

        // Add all active products
        $this->info('Aggiunta prodotti...');
        $products = Product::where('active', 1)->where('noindex', false)->get();
        foreach ($products as $product) {
            $url = $product->canonical_url();
            if ($this->shouldIncludeUrl($url)) {
                $sitemap->add(Url::create($url)
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setLastModificationDate($product->updated_at));
            }
        }
        $this->info("Aggiunti {$products->count()} prodotti");

        // Add all active categories
        $this->info('Aggiunta categorie...');
        $categories = Category::where('active', 1)->where('noindex', false)->get();
        foreach ($categories as $category) {
            $url = $category->canonical_url();
            if ($this->shouldIncludeUrl($url)) {
                // lastmod basato sull'ultimo prodotto aggiornato nella categoria
                $lastCategoryProductUpdate = Product::where('active', 1)
                    ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
                    ->max('updated_at');

                $sitemap->add(Url::create($url)
                    ->setPriority(0.9)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setLastModificationDate(
                        Carbon::parse($lastCategoryProductUpdate ?? $category->updated_at)
                    ));
            }
        }
        $this->info("Aggiunte {$categories->count()} categorie");

        // Add all content pages
        $this->info('Aggiunta pagine contenuti...');
        $pages = Page::whereNotNull('slug')
            ->where('slug', '!=', '')
            ->where('noindex', false)
            ->where(function ($query) {
                $query->whereNull('active')
                    ->orWhere('active', 1)
                    ->orWhere('active', true);
            })
            ->get();

        $pagesAdded = 0;
        foreach ($pages as $page) {
            $url = CanonicalUrl::absolute('/contenuti/'.$page->slug);
            if ($this->shouldIncludeUrl($url)) {
                $sitemap->add(Url::create($url)
                    ->setPriority(0.7)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setLastModificationDate($page->updated_at));
                $pagesAdded++;
            }
        }
        $this->info("Aggiunte {$pagesAdded} pagine contenuti");

        // Write sitemap to file
        $sitemap->writeToFile($sitemapPath);

        $fileSize = filesize($sitemapPath);
        if ($fileSize > 337) {
            $this->info('Generazione conclusa, dimensione file: '.$fileSize);
        } else {
            $this->error('Generazione conclusa, dimensione file anomala: '.$fileSize);
        }
    }

    /**
     * Check if URL should be included in sitemap
     */
    protected function shouldIncludeUrl(string $url): bool
    {
        foreach ($this->excludedRoutes as $excludedRoute) {
            if (str_contains($url, $excludedRoute)) {
                return false;
            }
        }

        return true;
    }
}
