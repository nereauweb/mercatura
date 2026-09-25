<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Phase 4(f): auth, account, contact, newsletter, CMS pages, blog. */
final class AccountPageTest extends TestCase
{
    /** @return list<string> */
    private function areaFiles(): array
    {
        $files = [
            resource_path('views/frontend/pages/contact.blade.php'),
            resource_path('views/frontend/pages/sent.blade.php'),
            resource_path('views/frontend/pages/newsletter.blade.php'),
            resource_path('views/frontend/pages/newsletter_success.blade.php'),
            resource_path('views/frontend/pages/page.blade.php'),
            resource_path('views/frontend/components/contact-details.blade.php'),
            resource_path('views/frontend/components/auth/card.blade.php'),
            resource_path('views/frontend/components/customer/table.blade.php'),
        ];
        foreach (['auth', 'customer', 'blog'] as $dir) {
            foreach (Finder::create()->files()->in(resource_path("views/frontend/{$dir}"))->name('*.blade.php') as $file) {
                $files[] = $file->getPathname();
            }
        }
        foreach (Finder::create()->files()->in(resource_path('views/livewire'))->name('frontend-*-table.blade.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    public function test_account_templates_contain_no_uikit_jquery_or_client_identity(): void
    {
        foreach ($this->areaFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\buk-[a-z]/', $source, basename($file).' still carries UIkit classes');
            $this->assertDoesNotMatchRegularExpression('/\$\(|jQuery|UIkit\.|admin\.layout/', $source, basename($file).' still uses jQuery, UIkit or the admin layout');
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern('google\.com\/maps|fg-|bg-blu|bg-arancione|bg-grigio'), $source, basename($file).' still carries client content');
        }
        $this->assertFileDoesNotExist(app_path('Http/Livewire/UserOrdersTable.php'));
    }

    public function test_guest_pages_render_with_one_h1(): void
    {
        foreach (['/accedi', '/registrati', '/contattaci', '/newsletter', '/blog', '/reset-password/token-x'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), $url);
            $this->assertTrue(str_contains($html, '<meta name="robots"') || str_contains($html, 'rel="canonical"'), $url.' has a robots directive or canonical');
        }
    }

    public function test_a_logged_in_user_opening_a_guest_page_lands_on_the_account_page(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->get('/accedi')->assertRedirect(route('frontend.auth.index'));
        // The reset link from the mail works even when the browser is still logged in.
        $this->actingAs($user)->get('/reset-password/token-x')->assertOk();
    }

    public function test_account_pages_require_login(): void
    {
        foreach (['/area-riservata', '/profilo', '/ordini', '/preventivi', '/messaggi'] as $url) {
            $this->get($url)->assertRedirect(route('frontend.auth.login'));
        }
    }

    public function test_a_customer_cannot_open_another_customers_order(): void
    {
        $owner = \App\Models\User::factory()->create();
        $other = \App\Models\User::factory()->create();
        $customerId = \App\Models\Customer::query()->create(['user_id' => $owner->id, 'email' => $owner->email, 'name' => 'Anna', 'surname' => 'Rossi', 'customer_type' => 'Privato'])->id;
        $order = \App\Models\Order::query()->create(['user_id' => $owner->id, 'customer_id' => $customerId, 'status' => 'requested', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'items_price' => 10, 'delivery_cost' => 0, 'total_price' => 10, 'total_tax' => 2.2, 'total_taxed_price' => 12.2]);
        $this->actingAs($owner)->get('/ordine/'.$order->id)->assertOk();
        $this->actingAs($other)->get('/ordine/'.$order->id)->assertNotFound();
    }

    public function test_the_contact_form_sends_the_admin_mail_and_keeps_the_message(): void
    {
        $before = \App\Models\Message::query()->count();
        $this->post('/contattaci/invia', ['name' => 'Anna', 'surname' => 'Rossi', 'email' => 'anna@example.com', 'phone' => '0123456789', 'subject' => 'Info', 'message' => 'Un messaggio di prova.', 'consent_gdpr' => '1'])
            ->assertOk();
        $this->assertDatabaseHas('messages', ['email' => 'anna@example.com', 'subject' => 'Info', 'read_at' => null]);
        $this->assertSame($before + 1, \App\Models\Message::query()->count(), 'one row per submission');
    }

    public function test_contact_and_newsletter_forms_carry_honeypot_and_consents(): void
    {
        $html = $this->get('/contattaci')->assertOk()->getContent();
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('name="consent_gdpr"', $html);
        $this->assertStringContainsString('name="subject"', $html);

        $html = $this->get('/newsletter')->assertOk()->getContent();
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('customerForm(', $html);
    }
}
