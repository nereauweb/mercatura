<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** v2b.6 stop criterion: no UIkit, jQuery, Bootstrap or CDN asset anywhere in the repository. */
class LegacyAssetsGoneTest extends TestCase
{
    public function test_no_legacy_markup_or_assets_in_any_view_or_script(): void
    {
        $files = Finder::create()->files()->in([resource_path('views'), resource_path('js'), resource_path('css'), resource_path('skins')])->name(['*.blade.php', '*.js', '*.css']);
        foreach ($files as $file) {
            $source = $file->getContents();
            $name = $file->getRelativePathname();
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, "{$name} carries UIkit classes");
            $this->assertDoesNotMatchRegularExpression('/jQuery|UIkit\.|select2|bootstrap@|cdn\.jsdelivr|code\.jquery|cdn\.ckeditor|kit\.fontawesome|getuikit/', $source, "{$name} references legacy scripts or CDN assets");
        }
    }

    public function test_legacy_public_assets_and_admin_code_are_gone(): void
    {
        foreach (['css/admin.css', 'css/colors.css', 'css/uikit.min.css', 'js/uikit.min.js', 'js/jquery-select-filter.min.js'] as $asset) {
            $this->assertFileDoesNotExist(public_path($asset));
        }
        $this->assertDirectoryDoesNotExist(resource_path('views/admin'));
        $this->assertSame([], glob(app_path('Http/Controllers/Admin*.php')) ?: []);
        $this->assertStringNotContainsString('rappasoft/laravel-livewire-tables', (string) file_get_contents(base_path('composer.json')));
        $this->get('/admin-legacy')->assertNotFound();
    }
}
