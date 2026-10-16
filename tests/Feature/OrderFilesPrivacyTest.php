<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomization;
use App\Models\User;
use App\Support\Orders\OrderFilesToPrivateDisk;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Order files live on private disks and leave only through the order file routes. */
final class OrderFilesPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    private User $other;

    private User $admin;

    private Order $order;

    private OrderItemCustomization $printing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake(Order::FILES_DISK);
        $this->owner = $this->user('customer');
        $this->other = $this->user('customer');
        $this->admin = $this->user('admin');
        $customerId = Customer::query()->create(['user_id' => $this->owner->id, 'email' => $this->owner->email, 'name' => 'Anna', 'surname' => 'Rossi', 'customer_type' => 'Privato'])->id;
        $this->order = Order::query()->create(['user_id' => $this->owner->id, 'customer_id' => $customerId, 'status' => 'requested', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'items_price' => 10, 'delivery_cost' => 0, 'total_price' => 10, 'total_tax' => 2.2, 'total_taxed_price' => 12.2]);
        $item = OrderItem::query()->create(['order_id' => $this->order->id, 'product_id' => 0, 'product_sku' => 'SKU-1', 'product_name' => 'Penna', 'quantity' => 100, 'price' => 10]);
        $this->printing = OrderItemCustomization::query()->create(['item_id' => $item->id, 'label' => 'Tampografia 1 colore', 'quantity' => 100, 'price' => 5]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => $role.'-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $user->assignRole($role);

        return $user;
    }

    public function test_an_order_attachment_is_private_and_reaches_only_its_customer_and_the_admin(): void
    {
        $this->actingAs($this->owner)->post(route('customer.order.upload-file', $this->order->id), [
            'image' => UploadedFile::fake()->createWithContent('logo.pdf', "%PDF-1.4\n".str_repeat('%', 2048)."\n%%EOF\n"),
        ])->assertRedirect();
        $media = $this->order->refresh()->getFirstMedia(Order::FILES);
        $this->assertNotNull($media);
        $this->assertSame(Order::FILES_DISK, $media->disk);
        Storage::disk(Order::FILES_DISK)->assertExists($media->getPathRelativeToRoot());
        Storage::disk('public')->assertDirectoryEmpty('/');

        $customerUrl = route('customer.order.file', [$this->order->id, $media->id]);
        $download = $this->actingAs($this->owner)->get($customerUrl);
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        $this->actingAs($this->other)->get($customerUrl)->assertNotFound();
        $this->actingAs($this->owner)->get(route('frontend.auth.order.show', $this->order->id))->assertOk()->assertSee($customerUrl, false)->assertDontSee('/storage/', false);

        $adminUrl = route('admin.orders.file', $media->id);
        $this->actingAs($this->admin)->get($adminUrl)->assertOk();
        $this->actingAs($this->owner)->get($adminUrl)->assertForbidden();
        auth()->logout();
        $this->get($customerUrl)->assertRedirect(route('frontend.auth.login'));
    }

    public function test_customization_artwork_is_private_and_reaches_only_its_customer_and_the_admin(): void
    {
        $orderPage = route('frontend.auth.order.show', $this->order->id);
        $this->actingAs($this->owner)->get($orderPage)->assertOk()->assertSee(__('frontend.account.print_file_missing'))->assertDontSee(__('frontend.account.print_file_ready'));
        $this->actingAs($this->owner)->put(route('customer.order.printings.upload-image', $this->printing->id), [
            'variant_image' => UploadedFile::fake()->createWithContent('marchio.pdf', "%PDF-1.4\n".str_repeat('%', 2048)."\n%%EOF\n"),
        ])->assertRedirect();
        $path = (string) $this->printing->refresh()->file;
        $this->assertStringStartsWith('orders/'.$this->order->id.'/printings/', $path);
        Storage::disk(OrderItemCustomization::FILE_DISK)->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $customerUrl = route('customer.order.printings.file', $this->printing->id);
        $this->actingAs($this->owner)->get($customerUrl)->assertOk();
        $this->actingAs($this->owner)->get($orderPage)->assertOk()->assertSee(__('frontend.account.print_file_ready'))->assertDontSee(__('frontend.account.print_file_missing'));
        $this->actingAs($this->other)->get($customerUrl)->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.orders.artwork', $this->printing->id))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.orders.artwork', $this->printing->id))->assertForbidden();
    }

    public function test_files_written_before_are_moved_off_the_public_disk_once(): void
    {
        $legacy = $this->order->addMediaFromString('vecchio file')->usingFileName('vecchio.pdf')->toMediaCollection(Order::FILES, 'public');
        Storage::disk('public')->assertExists($legacy->id.'/vecchio.pdf');
        $artwork = 'orders/'.$this->order->id.'/printings/printing_'.$this->printing->id.'_x.png';
        Storage::disk('public')->put($artwork, 'png');
        $this->printing->update(['file' => $artwork]);

        $this->assertSame(['media' => 1, 'artwork' => 1, 'missing' => 0], OrderFilesToPrivateDisk::run());

        $legacy->refresh();
        $this->assertSame(Order::FILES_DISK, $legacy->disk);
        Storage::disk(Order::FILES_DISK)->assertExists($legacy->id.'/vecchio.pdf');
        Storage::disk('public')->assertMissing($legacy->id.'/vecchio.pdf');
        Storage::disk(OrderItemCustomization::FILE_DISK)->assertExists($artwork);
        Storage::disk('public')->assertMissing($artwork);
        $this->assertSame($artwork, $this->printing->refresh()->file, 'the column keeps its value');
        $this->actingAs($this->owner)->get(route('customer.order.file', [$this->order->id, $legacy->id]))->assertOk();

        $this->assertSame(['media' => 0, 'artwork' => 0, 'missing' => 0], OrderFilesToPrivateDisk::run(), 'idempotent');
    }
}
