<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Skin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies one core view into the active skin, with the version header that
 * lets mercatura:skin-check tell when the core has moved on.
 */
final class SkinOverride extends Command
{
    protected $signature = 'mercatura:skin-override
        {view : Core view name, e.g. frontend.elements.product-card}
        {--skin= : Skin name; defaults to config("mercatura.skin")}
        {--force : Overwrite an existing skin file}';

    protected $description = 'Copy one core view into the skin directory';

    public function handle(Skin $skin): int
    {
        $name = Skin::sanitize((string) ($this->option('skin') ?: config('mercatura.skin')));

        if ($name === null) {
            $this->error('No skin selected: set MERCATURA_SKIN or pass --skin.');

            return self::FAILURE;
        }

        $view = (string) $this->argument('view');
        $relative = Skin::viewToPath($view);

        if (! Skin::isOverridable($relative)) {
            $this->error("{$view} is outside the overridable perimeter (frontend.*, livewire.frontend-*, mail.*).");

            return self::FAILURE;
        }

        $source = resource_path('views'.DIRECTORY_SEPARATOR.$relative);

        if (! is_file($source)) {
            $this->error("Core view not found: {$source}");

            return self::FAILURE;
        }

        $target = $skin->path($name, $relative);

        if (is_file($target) && ! $this->option('force')) {
            $this->error("Skin file already exists: {$target} (use --force to overwrite)");

            return self::FAILURE;
        }

        $version = Skin::versionOf($source);
        $header = "{{-- @mercatura-view {$view} @version {$version} --}}\n";

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $header.File::get($source));

        $this->info("Copied {$view} (version {$version}) to {$target}");

        return self::SUCCESS;
    }
}
