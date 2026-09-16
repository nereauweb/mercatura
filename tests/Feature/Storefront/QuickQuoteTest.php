<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Http\Controllers\FrontendQuotationController;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.4: the quick-quote modal shares the session store with the page and sends the same quotation. */
final class QuickQuoteTest extends TestCase
{
    use DatabaseTransactions;

    private CustomizationFixture $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->f = CustomizationFixture::create();
    }

    public function test_the_layout_mounts_the_modal_and_the_ctas_open_it_only_when_configured(): void
    {
        $page = $this->get('/prodotti/'.$this->f->product->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('quickQuote(', $page);
        $this->assertStringNotContainsString('quick-quote-open', $page);

        config(['mercatura.storefront.quick_quote' => 'modal']);
        $page = $this->get('/prodotti/'.$this->f->product->slug)->assertOk()->getContent();
        $this->assertStringContainsString('quickQuote(', $page);
        $this->assertStringContainsString('id="quick-quote"', $page);
        $this->assertStringContainsString("\$dispatch('quick-quote-open', {articleId: ".$this->f->a->id.'})', $page, 'the product CTA opens the modal with the current article');
        $this->assertStringContainsString("\$dispatch('quick-quote-open')", $page, 'the header pill opens the modal');
        $this->assertStringContainsString('data-quote-count', $page);
        $this->assertStringContainsString(__('frontend.quick_quote.products'), $page);
        $this->assertStringContainsString('name="customer[email]"', $page);

        $listing = $this->get('/prodotti')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/quick-quote-open\\\', \\{articleId: \\d+\\}/', $listing, 'the card CTA opens the modal with the main variant id');
    }

    public function test_the_endpoints_edit_the_same_session_store_as_the_page(): void
    {
        $list = $this->getJson('/preventivo/api/lista')->assertOk()->json();
        $this->assertSame([], $list['products']);

        $added = $this->postJson('/preventivo/api/aggiungi', ['article_id' => $this->f->a->id, 'quantity' => 150])->assertOk()->json();
        $this->assertSame(1, $added['count']);
        $row = $added['products'][0];
        $this->assertSame($this->f->product->sku, $row['sku']);
        $this->assertSame(150, $row['quantity']);
        $this->assertSame(__('frontend.quotation.no'), $row['printing']);
        $this->assertNotEmpty($row['colors'], 'the colours of the product are offered');
        $this->assertSame(1, count(session('quotation.products')), 'the page store is shared');

        $updated = $this->putJson('/preventivo/api/'.$row['id'].'/aggiorna', ['quantity' => 200, 'notes' => 'Logo a un colore', 'printing' => __('frontend.quotation.yes')])->assertOk()->json();
        $this->assertSame(200, $updated['products'][0]['quantity']);
        $this->assertSame('Logo a un colore', $updated['products'][0]['notes']);
        $this->assertSame(__('frontend.quotation.yes'), $updated['products'][0]['printing']);
        $this->putJson('/preventivo/api/'.$row['id'].'/aggiorna', ['printing' => 'forse'])->assertStatus(422);

        $page = $this->get('/preventivo')->assertOk()->getContent();
        $this->assertStringContainsString('Logo a un colore', $page, 'the page shows what the modal edited');

        $this->deleteJson('/preventivo/api/'.$row['id'].'/elimina')->assertOk()->assertJsonPath('count', 0);
        $this->putJson('/preventivo/api/'.$row['id'].'/aggiorna', ['quantity' => 1])->assertNotFound();
    }

    public function test_sending_from_the_modal_produces_the_same_rows_and_mails_as_the_page(): void
    {
        $customer = ['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'mario@example.com', 'company' => 'ACME', 'customer_type' => 'Azienda', 'activity' => 'Commercio', 'phone' => '0123456789'];
        $product = ['sku' => $this->f->product->sku, 'name' => $this->f->product->name, 'image' => '', 'color' => 'Blu', 'size' => 'Unica', 'quantity' => 100, 'printing' => 'Sì', 'notes' => 'Logo'];
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        // The page.
        $this->withSession(['quotation' => ['products' => ['p1' => $product + ['id' => 'p1']]]])
            ->post(action([FrontendQuotationController::class, 'store']), ['customer' => $customer, 'consent_gdpr' => '1', 'subscribe_newsletter' => '1'])->assertOk();
        $fromPage = Quotation::query()->latest('id')->firstOrFail();
        $pageMails = $transport->messages()->map(fn ($m) => (string) $m->getOriginalMessage()->getSubject())->all();
        $transport->flush();

        // The modal: missing email → 422, no products → 422, then the request.
        $this->postJson('/preventivo/api/invia', ['customer' => ['email' => ''], 'consent_gdpr' => '1'])->assertStatus(422)->assertJsonValidationErrors(['customer.email']);
        $this->postJson('/preventivo/api/invia', ['customer' => $customer, 'consent_gdpr' => '1'])->assertStatus(422)->assertJsonValidationErrors(['products']);
        $response = $this->withSession(['quotation' => ['products' => ['p2' => $product + ['id' => 'p2']]]])
            ->postJson('/preventivo/api/invia', ['customer' => $customer, 'consent_gdpr' => '1', 'subscribe_newsletter' => '1'])->assertOk();
        $response->assertJsonPath('sent', true)->assertJsonPath('count', 0);
        $fromModal = Quotation::query()->findOrFail((int) $response->json('quotation_id'));
        $this->assertNull(session('quotation.products'));
        $this->assertSame($customer, (array) session('quotation.customer'), 'the contact data stay for the next request');

        $strip = fn (Quotation $q): array => collect($q->toArray())->except(['id', 'created_at', 'updated_at'])->all();
        $this->assertSame($strip($fromPage), $strip($fromModal));
        $items = fn (Quotation $q): array => QuotationItem::query()->where('quotation_id', $q->id)->get()->map(fn (QuotationItem $i) => collect($i->toArray())->except(['id', 'quotation_id', 'created_at', 'updated_at'])->all())->all();
        $this->assertSame($items($fromPage), $items($fromModal));
        $this->assertSame('Sì', $items($fromModal)[0]['customization']);
        $modalMails = $transport->messages()->map(fn ($m) => (string) $m->getOriginalMessage()->getSubject())->all();
        $this->assertSame($pageMails, $modalMails);
        $this->assertCount(2, $modalMails, 'customer and merchant notifications');
    }
}
