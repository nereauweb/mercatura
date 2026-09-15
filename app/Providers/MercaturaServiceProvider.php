<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Skin;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the core/installation overlay (docs/ARCHITECTURE.md §3): the active
 * skin is resolved once from config('mercatura.skin') and applied before
 * any view is rendered. Registered first in bootstrap/providers.php.
 */
final class MercaturaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Skin::class, fn ($app) => new Skin($app));

        $this->app->make(Skin::class)->activate($this->app['config']->get('mercatura.skin'));

        $this->discoverConnectors();

        // The search provider is a Scout engine name: one switch for both.
        $search = $this->app['config']->get('mercatura.providers.search');
        if (is_string($search) && $search !== '') {
            $this->app['config']->set('scout.driver', $search);
        }
    }

    /**
     * Connector packages (docs/ARCHITECTURE.md §13) are ordinary Composer
     * packages. Installed through Composer they are discovered by Laravel;
     * checked out under connectors/<name>/ (git-ignored, for development or
     * for installations that deploy them by directory) they are loaded here
     * from their composer.json: PSR-4 autoload and Laravel providers.
     */
    private function discoverConnectors(): void
    {
        $manifests = glob(base_path('connectors/*/composer.json')) ?: [];
        if ($manifests === []) {
            return;
        }
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require base_path('vendor/autoload.php');
        foreach ($manifests as $manifest) {
            $package = json_decode((string) file_get_contents($manifest), true);
            if (! is_array($package)) {
                continue;
            }
            $dir = dirname($manifest);
            foreach ((array) ($package['autoload']['psr-4'] ?? []) as $namespace => $path) {
                $loader->addPsr4($namespace, $dir.'/'.trim((string) $path, '/'));
            }
            foreach ((array) ($package['extra']['laravel']['providers'] ?? []) as $provider) {
                if (class_exists($provider) && ! $this->app->getProvider($provider)) {
                    $this->app->register($provider);
                }
            }
        }
    }

    public function boot(): void
    {
        // Storefront anonymous components: <x-frontend::icon />, <x-frontend::product.card />
        // They resolve through the view finder, so a skin shadows them like any view
        // (resources/skins/<name>/frontend/components/...).
        Blade::anonymousComponentNamespace('frontend.components', 'frontend');
    }
}
