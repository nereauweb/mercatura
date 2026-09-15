<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/**
 * Phase 4 closing guard: the whole storefront surface (views, skins, bundle
 * sources) is free of UIkit, jQuery, Bootstrap, legacy assets and client
 * identity, and every storefront view carries its version header.
 */
final class StorefrontIntegrityTest extends TestCase
{
    /** @return iterable<\Symfony\Component\Finder\SplFileInfo> */
    private function storefrontFiles(): iterable
    {
        return Finder::create()->files()
            ->in([resource_path('views/frontend'), resource_path('views/mail'), resource_path('skins'), resource_path('js'), resource_path('css')])
            ->append(Finder::create()->files()->in(resource_path('views/livewire'))->name('frontend-*.blade.php'))
            ->name(['*.blade.php', '*.js', '*.css']);
    }

    public function test_no_legacy_markup_or_assets_anywhere_in_the_storefront(): void
    {
        foreach ($this->storefrontFiles() as $file) {
            $source = $file->getContents();
            $name = str_replace(base_path().'/', '', $file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, "{$name} carries UIkit classes");
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\.|select2|noUiSlider|matchHeight|bootstrap@|cdn\.jsdelivr|code\.jquery|kit\.fontawesome|legacy-assets/', $source, "{$name} references legacy scripts or CDN assets");
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('nereauweb|fg-bianco|fg-blu|bg-blu-|bg-arancione|bg-grigio|fg-grigio'), $source, "{$name} carries client identity or legacy colour classes");
        }
    }

    public function test_every_storefront_view_declares_its_version(): void
    {
        $views = Finder::create()->files()->in([resource_path('views/frontend'), resource_path('views/mail')])->name('*.blade.php')
            ->append(Finder::create()->files()->in(resource_path('views/livewire'))->name('frontend-*.blade.php'));
        foreach ($views as $file) {
            $this->assertStringContainsString('@mercatura-view', substr($file->getContents(), 0, 200), str_replace(base_path().'/', '', $file->getPathname()));
        }
    }

    public function test_no_storefront_view_extends_the_admin_layout(): void
    {
        foreach ($this->storefrontFiles() as $file) {
            $this->assertStringNotContainsString("extends('admin.", $file->getContents(), $file->getFilename());
        }
    }
}
