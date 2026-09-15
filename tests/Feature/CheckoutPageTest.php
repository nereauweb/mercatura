<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Phase 4(e): cart, checkout and quotation request. */
final class CheckoutPageTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        $files = [
            resource_path('views/frontend/pages/cart.blade.php'),
            resource_path('views/frontend/pages/quotation.blade.php'),
            resource_path('views/frontend/pages/quotation-sent.blade.php'),
            resource_path('views/frontend/partials/field-error.blade.php'),
            resource_path('views/frontend/partials/form-errors-summary.blade.php'),
            resource_path('js/storefront/customer-form.js'),
            resource_path('js/storefront/quotation-form.js'),
            resource_path('js/storefront/form-errors.js'),
            resource_path('js/storefront/captcha-refresh.js'),
        ];
        foreach (['pages/checkout', 'components/forms', 'components/checkout', 'components/cart'] as $dir) {
            foreach (Finder::create()->files()->in(resource_path("views/frontend/{$dir}"))->name('*.blade.php') as $file) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_checkout_templates_contain_no_uikit_jquery_or_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\./', $source, basename($file).' still uses jQuery or UIkit');
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('IT00X0000|fg-|bg-blu|bg-arancione|bg-grigio'), $source, basename($file).' still carries client content');
        }
        $this->assertFileDoesNotExist(resource_path('views/frontend/partials/customer-type-fields-script.blade.php'));
        $this->assertFileDoesNotExist(public_path('js/form-errors.js'));
    }

    public function test_cart_page_renders_empty_with_noindex(): void
    {
        $html = $this->get('/carrello/riepilogo')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringContainsString(__('frontend.cart.empty'), $html);
        $this->assertStringContainsString('aria-current="step"', $html);
    }

    public function test_checkout_requires_a_cart(): void
    {
        $this->get('/checkout/account')->assertRedirect(route('frontend.cart.index'));
        $this->get('/checkout/account/register')->assertRedirect(route('frontend.cart.index'));
    }

    public function test_quotation_page_renders_the_customer_form(): void
    {
        $html = $this->get('/preventivo')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('name="customer[email]"', $html);
        $this->assertStringContainsString('quotationForm(', $html);
        $this->assertStringContainsString(__('frontend.quotation.empty_title'), $html);
    }

    public function test_consents_link_to_the_configured_legal_pages(): void
    {
        config(['mercatura.legal_pages.privacy' => 'informativa-test']);

        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = \Illuminate\Support\Facades\Blade::render('<x-frontend::forms.consents :terms="false" />');

        $this->assertStringContainsString('/contenuti/informativa-test', $html);
    }
}
