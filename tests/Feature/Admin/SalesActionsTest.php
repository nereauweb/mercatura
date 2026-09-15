<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** v2b.2: every legacy sales action has a Filament equivalent. */
class SalesActionsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'sales-admin@example.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('admin');
        $user = User::query()->create(['name' => 'Cliente', 'email' => 'sales-customer@example.com', 'password' => Hash::make('password')]);
        $address = CustomerAddress::query()->create(['address' => 'Via Roma 1', 'city' => 'Roma', 'province' => 'RM', 'zip_code' => '00100', 'country' => 'Italia']);
        $this->customer = Customer::query()->create(['user_id' => $user->id, 'customer_type' => 'Azienda', 'email' => $user->email, 'name' => 'Giulia', 'surname' => 'Bianchi', 'company' => 'ACME', 'vat_code' => '01234567890', 'phone' => '0612345678', 'billing_address_id' => $address->id]);
        $this->order = Order::query()->create(['user_id' => $user->id, 'customer_id' => $this->customer->id, 'items_price' => 100, 'delivery_cost' => 16, 'total_price' => 116, 'total_tax' => 25.52, 'total_taxed_price' => 141.52, 'status' => 'requested', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid']);
        $this->actingAs($this->admin);
    }

    public function test_order_transition_action_updates_the_order(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('transition', ['status' => 'requested', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer', 'tracking_code' => ''])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $fresh = $this->order->refresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('paid', $fresh->payment_status);
    }

    public function test_order_confirmation_can_be_resent_and_files_managed(): void
    {
        Storage::fake('public');
        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('resendConfirmation')->assertHasNoActionErrors()
            ->callAction('uploadFile', ['file' => UploadedFile::fake()->create('bozza.pdf', 20, 'application/pdf')])->assertHasNoActionErrors();

        $this->assertCount(1, $transport->messages());
        $media = $this->order->refresh()->getMedia('order_files');
        $this->assertCount(1, $media);
        $this->assertSame('bozza.pdf', $media[0]->file_name);

        Livewire::test(ViewOrder::class, ['record' => $this->order->id])
            ->callAction('deleteFile', ['media_id' => $media[0]->id])->assertHasNoActionErrors();
        $this->assertCount(0, $this->order->refresh()->getMedia('order_files'));
    }

    public function test_customer_edit_saves_profile_and_addresses(): void
    {
        Livewire::test(EditCustomer::class, ['record' => $this->customer->id])
            ->assertSchemaStateSet(['billing.city' => 'Roma'])
            ->fillForm([
                'customer_type' => 'Pubblica amministrazione', 'company' => 'Comune di Esempio', 'vat_code' => '09876543210',
                'pec' => 'protocollo@pec.example.com', 'ipa_code' => 'ABC123', 'cig_code' => 'Z1A2B3C4D5', 'phone' => '0551234567',
                'billing.city' => 'Firenze', 'billing.province' => 'FI',
                'shipping.address' => 'Piazza del Municipio 1', 'shipping.city' => 'Firenze', 'shipping.province' => 'FI', 'shipping.zip_code' => '50122', 'shipping.country' => 'Italia',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $customer = $this->customer->refresh();
        $this->assertSame('Pubblica amministrazione', $customer->customer_type);
        $this->assertSame('ABC123', $customer->ipa_code);
        $this->assertSame('Firenze', $customer->billing_address?->city);
        $this->assertSame('Piazza del Municipio 1', $customer->shipping_address?->address);
    }

    public function test_customer_edit_enforces_the_storefront_rules(): void
    {
        Livewire::test(EditCustomer::class, ['record' => $this->customer->id])
            ->fillForm(['customer_type' => 'Azienda', 'vat_code' => '123'])
            ->call('save')
            ->assertHasFormErrors(['vat_code']);
    }

    public function test_quotation_can_be_deleted(): void
    {
        $quotation = Quotation::query()->create(['customer_email' => 'q@example.com', 'customer_name' => 'Marco', 'customer_surname' => 'Verdi']);

        Livewire::test(ViewQuotation::class, ['record' => $quotation->id])->callAction(DeleteAction::class)->assertHasNoActionErrors();

        $this->assertNotNull(Quotation::withTrashed()->find($quotation->id)?->deleted_at);
    }
}
