<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Str;
use Illuminate\Translation\FileLoader;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;

/**
 * The skin overlay: one directory under resources/skins/ whose files shadow
 * the core storefront views, lang files and brand defaults.
 *
 * See docs/ARCHITECTURE.md §3 and §4. A skin is an installation property
 * read once at boot; there is no runtime switching.
 */
final class Skin
{
    /** Directory under resources/ that holds the skins. */
    public const DIRECTORY = 'skins';

    /** Header written into overridden views: {{-- @mercatura-view name @version n --}} */
    public const VERSION_HEADER = '/@mercatura-view\s+(?<view>[\w.\-]+)\s+@version\s+(?<version>\d+)/';

    /**
     * Prefixes of the relative view paths (under resources/views) that a
     * skin may override. Anything else, admin views in particular, is
     * outside the perimeter.
     *
     * @var list<string>
     */
    public const OVERRIDABLE_PREFIXES = [
        'frontend/',
        'livewire/frontend-',
        'mail/',
    ];

    private ?string $active = null;

    public function __construct(private readonly Application $app) {}

    /** Sanitise a skin name; null when empty or invalid. */
    public static function sanitize(?string $name): ?string
    {
        $name = strtolower(trim((string) $name));

        return preg_match('/^[a-z0-9_-]+$/', $name) === 1 ? $name : null;
    }

    /** The active skin name, or null when the core default renders. */
    public function name(): ?string
    {
        return $this->active;
    }

    /** Absolute path of a skin directory, whether or not it exists. */
    public function path(string $name, string $sub = ''): string
    {
        $path = $this->app->resourcePath(self::DIRECTORY.DIRECTORY_SEPARATOR.$name);

        return $sub === '' ? $path : $path.DIRECTORY_SEPARATOR.ltrim($sub, '/');
    }

    /** Whether a skin directory exists for the given name. */
    public function exists(?string $name): bool
    {
        $name = self::sanitize($name);

        return $name !== null && is_dir($this->path($name));
    }

    /**
     * Activate a skin: prepend its directory to the view finder, add its
     * lang directory to the translator, merge its brand.php over
     * config('brand') and register its service provider when present.
     */
    public function activate(?string $name): void
    {
        $name = self::sanitize($name);

        if ($name === null || ! is_dir($path = $this->path($name))) {
            $this->active = null;
            $this->app['config']->set('mercatura.skin', null);

            return;
        }

        $this->active = $name;
        $this->app['config']->set('mercatura.skin', $name);

        $prepend = static function (ViewFactory $view) use ($path): void {
            $finder = $view->getFinder();
            if ($finder instanceof FileViewFinder) {
                $finder->prependLocation($path);
            }
        };
        $this->app->afterResolving('view', $prepend);
        if ($this->app->resolved('view')) {
            $prepend($this->app['view']);
        }

        $lang = $path.DIRECTORY_SEPARATOR.'lang';
        if (is_dir($lang)) {
            $this->app->afterResolving('translation.loader', function (Loader $loader) use ($lang): void {
                if ($loader instanceof FileLoader) {
                    $loader->addPath($lang);
                }
            });
            if ($this->app->resolved('translation.loader')) {
                $loader = $this->app['translation.loader'];
                if ($loader instanceof FileLoader) {
                    $loader->addPath($lang);
                }
            }
        }

        $brand = $path.DIRECTORY_SEPARATOR.'brand.php';
        if (is_file($brand)) {
            $overrides = require $brand;
            if (is_array($overrides)) {
                $this->app['config']->set('brand', array_replace_recursive($this->app['config']->get('brand', []), $overrides));
            }
        }

        $provider = $path.DIRECTORY_SEPARATOR.'SkinServiceProvider.php';
        if (is_file($provider)) {
            require_once $provider;
            $class = self::providerClass($name);
            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }

    /** Fully qualified class name expected in a skin's SkinServiceProvider.php. */
    public static function providerClass(string $name): string
    {
        return 'Mercatura\\Skins\\'.Str::studly($name).'\\SkinServiceProvider';
    }

    /** Whether a relative view path (e.g. frontend/pages/home.blade.php) is overridable. */
    public static function isOverridable(string $relativePath): bool
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        foreach (self::OVERRIDABLE_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Convert a view name (frontend.pages.home) to its relative file path. */
    public static function viewToPath(string $view): string
    {
        return str_replace('.', '/', $view).'.blade.php';
    }

    /** Convert a relative file path back to a view name. */
    public static function pathToView(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = preg_replace('/\.blade\.php$/', '', $relativePath) ?? $relativePath;

        return str_replace('/', '.', $relativePath);
    }

    /** Version declared in a view file header, 1 when there is none. */
    public static function versionOf(string $file): int
    {
        $head = (string) file_get_contents($file, false, null, 0, 512);

        return preg_match(self::VERSION_HEADER, $head, $m) === 1 ? (int) $m['version'] : 1;
    }
}
