<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\Orders\StoreOrderItemCustomizations;
use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemArticle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Customizations\LinePricer;
use App\Support\Customizations\Pricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * An admin, three customers and a few orders and quotations in different
 * states, so the account area and the admin have something to show.
 * Every account uses the password "password".
 */
class DemoCustomersSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const ADMIN_EMAIL = 'admin@example.com';

    public const CUSTOMERS = [
        ['cliente.azienda@example.com', 'Giulia Bianchi', 'Azienda', 'Giulia', 'Bianchi', 'Esempio S.r.l.', 'Servizi', 'IT01234567890', 'ABCDEFG', 'Via dei Fornai 12', 'Bologna', 'BO', '40121'],
        ['cliente.privato@example.com', 'Marco Verdi', 'Privato', 'Marco', 'Verdi', null, null, null, null, 'Corso Italia 5', 'Torino', 'TO', '10121'],
        ['cliente.pa@example.com', 'Comune di Esempio', 'Pubblica amministrazione', 'Anna', 'Neri', 'Comune di Esempio', 'Ente locale', 'IT09876543210', null, 'Piazza del Municipio 1', 'Firenze', 'FI', '50122'],
    ];

    public function run(): void
    {
        $admin = User::query()->firstOrCreate(['email' => self::ADMIN_EMAIL], ['name' => 'Amministratore', 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]);
        $admin->syncRoles(['admin']);

        $customers = [];
        foreach (self::CUSTOMERS as [$email, $name, $type, $firstName, $surname, $company, $activity, $vat, $sdi, $street, $city, $province, $zip]) {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()]);
            $user->assignRole('customer');
            $address = CustomerAddress::query()->create(['address' => $street, 'city' => $city, 'province' => $province, 'zip_code' => $zip, 'country' => 'Italia']);
            $customers[] = Customer::query()->create([
                'user_id' => $user->id, 'customer_type' => $type, 'email' => $email, 'phone' => '+39 000 000 0000',
                'name' => $firstName, 'surname' => $surname, 'company' => $company, 'activity' => $activity,
                'vat_code' => $vat, 'sdi_code' => $sdi, 'tax_code' => $type === 'Privato' ? 'VRDMRC80A01L219X' : null,
                'billing_address_id' => $address->id, 'shipping_address_id' => $address->id,
            ]);
        }

        $this->seedMessages($customers);

        $products = Product::query()->where('sku', 'like', DemoCatalogSeeder::SKU_PREFIX.'%')->orderBy('id')->get()->values();
        if ($products->isEmpty()) {
            return;
        }

        $orders = [
            [$customers[0], 'bank_transfer', 'requested', 'unpaid', null, 40, [[0, 100], [5, 250]]],
            [$customers[0], 'stripe', 'paid', 'paid', null, 12, [[3, 50]]],
            [$customers[1], 'paypal', 'delivering', 'paid', 'TRK-DEMO-0001', 30, [[9, 24], [13, 200]]],
            [$customers[2], 'bank_transfer', 'cancelled', 'unpaid', null, 60, [[16, 100]]],
        ];
        foreach ($orders as [$customer, $method, $status, $paymentStatus, $tracking, $daysAgo, $lines]) {
            $this->seedOrder($customer, $method, $status, $paymentStatus, $tracking, $daysAgo, $lines, $products);
        }

        foreach ([[$customers[0], 20, [[7, 150]]], [$customers[1], 3, [[0, 80], [4, 80]]]] as [$customer, $daysAgo, $lines]) {
            $quotation = Quotation::query()->create([
                'customer_email' => $customer->email, 'customer_type' => $customer->customer_type, 'customer_company' => $customer->company,
                'customer_name' => $customer->name, 'customer_surname' => $customer->surname, 'customer_phone' => $customer->phone, 'customer_activity' => $customer->activity,
            ]);
            $quotation->created_at = now()->subDays($daysAgo);
            $quotation->save();
            foreach ($lines as [$index, $quantity]) {
                $product = $products[$index];
                $variant = $product->main_variant_relationship;
                $quotation->items()->create([
                    'sku' => $product->sku, 'name' => $product->name, 'quantity' => $quantity, 'customization' => __('frontend.quotation.yes'),
                    'image' => (string) $product->cover(true), 'color' => (string) $variant?->color?->label, 'size' => (string) $variant?->size?->shown_label(), 'notes' => 'Logo a un colore',
                ]);
            }
        }
    }

    /**
     * A contact message and two user reports (docs/06_USER_REPORTS.md) for the panel's inbox.
     *
     * @param  list<Customer>  $customers
     */
    private function seedMessages(array $customers): void
    {
        $rows = [
            [$customers[0], MessageType::Contact, null, 'Richiesta catalogo', 'Potete inviarmi il catalogo aggiornato delle borracce?', null, null, 9],
            [$customers[1], MessageType::Report, ReportKind::Bug, 'Il totale del carrello non si aggiorna', 'Cambiando la quantità di un articolo il totale resta quello di prima finché non ricarico la pagina.', '/carrello/riepilogo', ['area' => 'storefront', 'viewport' => '390x844'], 2],
            [$customers[2], MessageType::Report, ReportKind::Question, 'Dove trovo le fatture?', 'Nell\'area riservata non vedo le fatture degli ordini già pagati.', '/ordini', ['area' => 'account', 'route' => 'frontend.auth.order.list', 'viewport' => '1366x768'], 1],
        ];
        foreach ($rows as [$customer, $type, $kind, $subject, $text, $path, $context, $daysAgo]) {
            $message = Message::query()->create([
                'type' => $type, 'user_id' => $customer->user_id, 'reporter_role' => ReporterRole::Customer, 'kind' => $kind,
                'email' => $customer->email, 'name' => $customer->name, 'surname' => $customer->surname, 'company' => $customer->company,
                'subject' => $subject, 'message' => $text, 'page_url' => $path !== null ? url($path) : null, 'context' => $context,
                'consent_gdpr' => $type === MessageType::Contact,
            ]);
            $message->created_at = now()->subDays($daysAgo);
            $message->save();
        }
    }

    /**
     * @param  list<array{0: int, 1: int}>  $lines  [product index, quantity]
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function seedOrder(Customer $customer, string $method, string $status, string $paymentStatus, ?string $tracking, int $daysAgo, array $lines, $products): void
    {
        $address = $customer->billing_address;
        $order = new Order([
            'user_id' => $customer->user_id, 'customer_id' => $customer->id, 'items_price' => 0, 'delivery_cost' => 16, 'total_price' => 0, 'total_tax' => 0, 'total_taxed_price' => 0,
            'address' => $address?->address, 'city' => $address?->city, 'province' => $address?->province, 'zip_code' => $address?->zip_code, 'country' => $address?->country,
            'notes' => $method === 'bank_transfer' ? 'Consegna al piano, orario ufficio.' : null, 'tracking_code' => $tracking,
            'status' => $status, 'payment_method' => $method, 'payment_status' => $paymentStatus,
            'payment_transaction' => $method === 'paypal' ? 'DEMO-PAYPAL-'.$daysAgo : null,
        ]);
        $order->created_at = now()->subDays($daysAgo);
        $order->updated_at = now()->subDays($daysAgo);
        $order->save();

        $itemsPrice = 0.0;
        foreach ($lines as [$index, $quantity]) {
            /** @var Product $product */
            $product = $products[$index];
            /** @var ProductVariant|null $variant */
            $variant = $product->main_variant_relationship;
            if ($variant === null) {
                continue;
            }
            $printing = $variant->defaultCustomization();
            $printColor = $printing?->areas()->first()?->options()->first();
            $line = app(LinePricer::class)->price([[$variant->id, $quantity]], $printColor ? [$printColor->id] : [], false);
            $unitPrice = $line->articles[0]->unitPrice;
            $linePrice = round($line->price, 2);

            $item = OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $product->id, 'product_sku' => $product->sku, 'product_name' => $product->name,
                'product_image_url' => (string) $product->cover(true), 'quantity' => $quantity, 'price' => $linePrice,
            ]);
            OrderItemArticle::query()->create([
                'item_id' => $item->id, 'article_id' => $variant->id, 'article_sku' => $variant->sku, 'article_size_label' => (string) $variant->size?->shown_label(),
                'article_color_label' => (string) $variant->color?->label, 'article_image_url' => (string) $variant->cover(true),
                'quantity' => $quantity, 'unit_price' => $unitPrice, 'price' => round($quantity * $unitPrice, 2),
            ]);
            app(StoreOrderItemCustomizations::class)->handle($item, $line);
            $itemsPrice += $linePrice;
        }

        $order->items_price = round($itemsPrice, 2);
        $order->delivery_cost = Pricing::deliveryCost($itemsPrice);
        $order->total_price = round($itemsPrice + (float) $order->delivery_cost, 2);
        $order->total_tax = Pricing::vat((float) $order->total_price);
        $order->total_taxed_price = round((float) $order->total_price + (float) $order->total_tax, 2);
        $order->save();
    }
}
