<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Skin;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SkinOverlayTest extends TestCase
{
    private string $tempSkin = 'zz-overlay-test';

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('skins/'.$this->tempSkin));

        parent::tearDown();
    }

    public function test_core_views_render_when_no_skin_is_active(): void
    {
        $this->assertNull($this->app->make(Skin::class)->name());
        $this->assertStringStartsWith(resource_path('views'), view()->getFinder()->find('frontend.pages.home'));
        $this->assertSame('Mercatura', config('brand.name'));
        $this->assertSame('Benvenuto su Mercatura', __('frontend.home.hero_title', ['brand' => 'Mercatura']));
    }

    public function test_demo_skin_shadows_views_lang_and_brand(): void
    {
        $this->app->make(Skin::class)->activate('demo');

        $this->assertSame('demo', config('mercatura.skin'));
        $this->assertStringStartsWith(resource_path('skins/demo'), view()->getFinder()->find('frontend.pages.home'));
        $this->assertStringStartsWith(resource_path('skins/demo'), view()->getFinder()->find('frontend.public.header'));
        $this->assertStringStartsWith(resource_path('views'), view()->getFinder()->find('frontend.public.footer'), 'files the skin does not override fall back to the core');

        $this->assertSame('Mercatura Demo', config('brand.name'));
        $this->assertSame('/skins/demo/logo.svg', config('brand.logo'));
        $this->assertSame('IT', config('brand.contact.address.country'), 'brand overrides merge over the defaults');

        $this->assertSame('Mercatura Demo — skin demo attiva', __('frontend.home.hero_title', ['brand' => 'Mercatura Demo']));
        $this->assertSame('Prodotti personalizzati per la tua azienda: scegli, configura, ordina.', __('frontend.home.hero_text'), 'lang keys the skin does not override keep the core value');
    }

    public function test_demo_skin_home_renders(): void
    {
        $this->app->make(Skin::class)->activate('demo');

        $this->get('/')
            ->assertOk()
            ->assertSee('skin demo attiva')
            ->assertSee('/skins/demo/demo.css', false)
            ->assertSee('mercatura-demo-ribbon', false);
    }

    public function test_invalid_or_unknown_skin_names_fall_back_to_core(): void
    {
        $skin = $this->app->make(Skin::class);

        $skin->activate('../etc');
        $this->assertNull($skin->name());

        $skin->activate('does-not-exist');
        $this->assertNull($skin->name());
        $this->assertNull(config('mercatura.skin'));
        $this->assertStringStartsWith(resource_path('views'), view()->getFinder()->find('frontend.pages.home'));
    }

    public function test_skin_service_provider_is_registered_when_present(): void
    {
        $path = resource_path('skins/'.$this->tempSkin);
        File::ensureDirectoryExists($path);
        File::put($path.'/SkinServiceProvider.php', <<<'PHP'
<?php
namespace Mercatura\Skins\ZzOverlayTest;
class SkinServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void { $this->app->instance('overlay-test.registered', true); }
}
PHP);

        $this->app->make(Skin::class)->activate($this->tempSkin);

        $this->assertTrue($this->app->bound('overlay-test.registered'));
    }

    public function test_skin_check_passes_for_demo(): void
    {
        $this->artisan('mercatura:skin-check', ['skin' => 'demo'])
            ->expectsOutputToContain('Skin check passed')
            ->assertExitCode(0);
    }

    public function test_skin_check_fails_on_views_without_core_counterpart_or_outside_perimeter(): void
    {
        $path = resource_path('skins/'.$this->tempSkin);
        File::ensureDirectoryExists($path.'/frontend/pages');
        File::ensureDirectoryExists($path.'/admin');
        File::put($path.'/frontend/pages/no-such-page.blade.php', 'x');
        File::put($path.'/admin/dashboard.blade.php', 'x');

        $this->artisan('mercatura:skin-check', ['skin' => $this->tempSkin])
            ->expectsOutputToContain('no core counterpart')
            ->expectsOutputToContain('outside the overridable perimeter')
            ->assertExitCode(1);
    }

    public function test_skin_override_copies_a_core_view_with_a_version_header(): void
    {
        $this->artisan('mercatura:skin-override', ['view' => 'frontend.public.footer', '--skin' => $this->tempSkin])
            ->assertExitCode(0);

        $file = resource_path('skins/'.$this->tempSkin.'/frontend/public/footer.blade.php');
        $this->assertFileExists($file);
        $version = Skin::versionOf(resource_path('views/frontend/public/footer.blade.php'));
        $this->assertStringStartsWith("{{-- @mercatura-view frontend.public.footer @version {$version} --}}", File::get($file));

        $this->artisan('mercatura:skin-override', ['view' => 'admin.pages.dashboard', '--skin' => $this->tempSkin])
            ->assertExitCode(1);
    }
}
