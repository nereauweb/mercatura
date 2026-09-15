<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Skin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Verifies a skin against the core: every skin file must shadow a core
 * file inside the overridable perimeter (docs/ARCHITECTURE.md §3), lang
 * files must have a core group, brand keys must exist in config/brand.php
 * and overridden views should carry an up-to-date version header.
 */
final class SkinCheck extends Command
{
    protected $signature = 'mercatura:skin-check
        {skin : Skin directory name under resources/skins}
        {--list : Also list every core view the skin may override}';

    protected $description = 'Check a skin against the core: unknown overrides fail, stale versions warn';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(Skin $skin): int
    {
        $name = Skin::sanitize((string) $this->argument('skin'));

        if ($name === null) {
            $this->error('Invalid skin name: only [a-z0-9_-] is allowed.');

            return self::FAILURE;
        }

        $path = $skin->path($name);

        if (! is_dir($path)) {
            $this->error("Skin directory not found: {$path}");

            return self::FAILURE;
        }

        $views = $this->checkViews($path);
        $this->checkLang($path);
        $this->checkBrand($path);
        $this->checkProvider($path, $name);

        $this->line("Skin <info>{$name}</info> at {$path}");
        $this->line('Overridden views: '.count($views));
        foreach ($views as $view) {
            $this->line("  {$view}");
        }

        if ($this->option('list')) {
            $this->newLine();
            $this->line('Overridable core views:');
            foreach ($this->coreViews() as $view) {
                $this->line("  {$view}");
            }
        }

        foreach ($this->warnings as $warning) {
            $this->warn("WARN  {$warning}");
        }
        foreach ($this->errors as $error) {
            $this->error("FAIL  {$error}");
        }

        if ($this->errors !== []) {
            return self::FAILURE;
        }

        $this->info('Skin check passed'.($this->warnings === [] ? '.' : ' with warnings.'));

        return self::SUCCESS;
    }

    /** @return list<string> overridden view names */
    private function checkViews(string $path): array
    {
        $views = [];
        $viewsRoot = resource_path('views');

        foreach ($this->bladeFiles($path) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $view = Skin::pathToView($relative);

            if (! Skin::isOverridable($relative)) {
                $this->errors[] = "{$relative} is outside the overridable perimeter (frontend/**, livewire/frontend-*, mail/**)";

                continue;
            }

            $core = $viewsRoot.DIRECTORY_SEPARATOR.$relative;

            if (! is_file($core)) {
                $this->errors[] = "{$relative} has no core counterpart (typo, or the core renamed the view)";

                continue;
            }

            $views[] = $view;

            $head = (string) file_get_contents($file->getPathname(), false, null, 0, 512);
            if (preg_match(Skin::VERSION_HEADER, $head, $m) !== 1) {
                $this->warnings[] = "{$relative} has no @mercatura-view header; create overrides with mercatura:skin-override";

                continue;
            }

            if ($m['view'] !== $view) {
                $this->warnings[] = "{$relative} header names view {$m['view']} but the file overrides {$view}";
            }

            $coreVersion = Skin::versionOf($core);
            if ($coreVersion > (int) $m['version']) {
                $this->warnings[] = "{$relative} was copied from version {$m['version']}, the core is at version {$coreVersion}: review the override";
            }
        }

        sort($views);

        return $views;
    }

    private function checkLang(string $path): void
    {
        $lang = $path.DIRECTORY_SEPARATOR.'lang';

        if (! is_dir($lang)) {
            return;
        }

        foreach (Finder::create()->files()->in($lang)->name('*.php') as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $core = lang_path($relative);

            if (! is_file($core)) {
                $this->errors[] = "lang/{$relative} has no core counterpart in lang/";

                continue;
            }

            $coreKeys = array_keys(\Illuminate\Support\Arr::dot((array) require $core));
            $skinKeys = array_keys(\Illuminate\Support\Arr::dot((array) require $file->getPathname()));
            $unknown = array_diff($skinKeys, $coreKeys);

            if ($unknown !== []) {
                $this->warnings[] = "lang/{$relative} defines keys the core does not have: ".implode(', ', array_slice($unknown, 0, 5)).(count($unknown) > 5 ? ', …' : '');
            }
        }
    }

    private function checkBrand(string $path): void
    {
        $brand = $path.DIRECTORY_SEPARATOR.'brand.php';

        if (! is_file($brand)) {
            return;
        }

        $overrides = require $brand;

        if (! is_array($overrides)) {
            $this->errors[] = 'brand.php must return an array';

            return;
        }

        $coreKeys = array_keys(\Illuminate\Support\Arr::dot((array) require config_path('brand.php')));
        $skinKeys = array_keys(\Illuminate\Support\Arr::dot($overrides));
        $unknown = array_diff($skinKeys, $coreKeys);

        if ($unknown !== []) {
            $this->warnings[] = 'brand.php sets keys the core does not define: '.implode(', ', $unknown);
        }
    }

    private function checkProvider(string $path, string $name): void
    {
        $file = $path.DIRECTORY_SEPARATOR.'SkinServiceProvider.php';

        if (! is_file($file)) {
            return;
        }

        require_once $file;

        if (! class_exists($class = Skin::providerClass($name))) {
            $this->errors[] = "SkinServiceProvider.php does not declare {$class}";
        }
    }

    /** @return iterable<\Symfony\Component\Finder\SplFileInfo> */
    private function bladeFiles(string $path): iterable
    {
        return Finder::create()->files()->in($path)->exclude(['lang'])->name('*.blade.php');
    }

    /** @return list<string> */
    private function coreViews(): array
    {
        $views = [];

        foreach ($this->bladeFiles(resource_path('views')) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (Skin::isOverridable($relative)) {
                $views[] = Skin::pathToView($relative);
            }
        }

        sort($views);

        return $views;
    }
}
