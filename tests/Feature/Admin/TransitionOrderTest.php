<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Orders\TransitionOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** docs/02_V2B_ADMIN.md §4.3: the legacy order status machine, mail by mail. */
class TransitionOrderTest extends TestCase
{
    use DatabaseTransactions;

    private function order(string $status = 'requested', string $paymentStatus = 'unpaid', string $method = 'bank_transfer'): Order
    {
        $user = User::query()->create(['name' => 'C', 'email' => 'transition-'.uniqid().'@example.com', 'password' => Hash::make('x')]);
        $customer = Customer::query()->create(['user_id' => $user->id, 'customer_type' => 'Azienda', 'email' => $user->email, 'name' => 'Giulia', 'surname' => 'Bianchi', 'company' => 'ACME']);

        return Order::query()->create(['user_id' => $user->id, 'customer_id' => $customer->id, 'items_price' => 100, 'delivery_cost' => 16, 'total_price' => 116, 'total_tax' => 25.52, 'total_taxed_price' => 141.52, 'status' => $status, 'payment_method' => $method, 'payment_status' => $paymentStatus]);
    }

    /** @return list<string> */
    private function sentSubjects(): array
    {
        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();

        return $transport instanceof ArrayTransport ? $transport->messages()->map(fn ($m) => (string) $m->getOriginalMessage()->getSubject())->all() : [];
    }

    public function test_payment_paid_promotes_a_requested_order_and_mails_the_customer_once(): void
    {
        $order = $this->order();

        $sent = app(TransitionOrder::class)->handle($order, ['status' => 'requested', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer', 'tracking_code' => null]);

        $this->assertSame(['payed'], $sent);
        $this->assertSame('paid', $order->fresh()?->status);
        $this->assertCount(1, $this->sentSubjects());
        $this->assertStringContainsString('Pagamento ricevuto', $this->sentSubjects()[0]);
    }

    public function test_tracking_code_mails_shipped_and_wins_over_a_status_change(): void
    {
        $order = $this->order('paid', 'paid');

        $sent = app(TransitionOrder::class)->handle($order, ['status' => 'delivering', 'payment_status' => 'paid', 'tracking_code' => 'TRK-1']);

        $this->assertSame(['sent'], $sent);
        $this->assertSame('delivering', $order->fresh()?->status);
        $this->assertStringContainsString('spedito', $this->sentSubjects()[0]);
    }

    public function test_status_changes_mail_updated_or_cancelled(): void
    {
        $order = $this->order('paid', 'paid');
        $this->assertSame(['updated'], app(TransitionOrder::class)->handle($order, ['status' => 'processing', 'payment_status' => 'paid']));
        $this->assertSame(['cancelled'], app(TransitionOrder::class)->handle($order, ['status' => 'cancelled', 'payment_status' => 'paid']));
        $this->assertSame([], app(TransitionOrder::class)->handle($order, ['status' => 'cancelled', 'payment_status' => 'paid']), 'no change, no mail');
        $this->assertCount(2, $this->sentSubjects());
    }

    public function test_payment_method_changes_only_while_draft_or_requested(): void
    {
        $order = $this->order('requested');
        app(TransitionOrder::class)->handle($order, ['payment_method' => 'stripe']);
        $this->assertSame('stripe', $order->fresh()?->payment_method);

        $paid = $this->order('paid', 'paid', 'stripe');
        app(TransitionOrder::class)->handle($paid, ['payment_method' => 'paypal']);
        $this->assertSame('stripe', $paid->fresh()?->payment_method);
    }
}
