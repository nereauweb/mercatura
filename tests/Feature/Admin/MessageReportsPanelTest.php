<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Messages\SubmitReport;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ReportAccess;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** R2 of docs/06_USER_REPORTS.md: reports in the panel inbox, protected attachment downloads. */
final class MessageReportsPanelTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
        $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'reports-admin@example.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('admin');
        $this->customer = User::query()->create(['name' => 'Cliente', 'email' => 'reports-customer@example.com', 'password' => Hash::make('password')]);
        $this->customer->assignRole('customer');
    }

    private function report(): Message
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        $request = Request::create('http://localhost/segnala', 'POST', server: ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_USER_AGENT' => 'PanelTest/1.0']);
        $this->app->instance('request', $request);

        $message = app(SubmitReport::class)->handle(
            ['kind' => 'bug', 'title' => 'Prezzo errato nel carrello', 'description' => 'Il totale non comprende la stampa.', 'page_url' => 'http://localhost/ordini', 'js_errors' => ['ReferenceError: cart is not defined']],
            [UploadedFile::fake()->createWithContent('schermata carrello.png', $png)],
            $this->customer,
            $request,
        );
        $this->assertNotNull($message);

        return $message;
    }

    public function test_the_admin_finds_a_report_in_the_inbox_with_its_details_and_attachments(): void
    {
        $message = $this->report();
        $contact = Message::query()->create(['email' => 'altro@example.com', 'name' => 'Altro', 'subject' => 'Info', 'message' => 'Contatto normale.']);
        $this->actingAs($this->admin);

        $list = Livewire::test(ListMessages::class);
        $list->assertOk();
        $list->assertCanSeeTableRecords([$message, $contact])
            ->filterTable('type', 'report')
            ->assertCanSeeTableRecords([$message])
            ->assertCanNotSeeTableRecords([$contact]);

        $media = $message->getFirstMedia(Message::ATTACHMENTS);
        $this->assertNotNull($media);
        $view = Livewire::test(ViewMessage::class, ['record' => $message->id]);
        $view->assertOk();
        $view->assertSee('Prezzo errato nel carrello')
            ->assertSee('frontend.auth.order.list')
            ->assertSee('ReferenceError: cart is not defined')
            ->assertSee('schermata carrello.png')
            ->assertSee(route('admin.messages.attachment', $media->id), false)
            ->assertSee(route('admin.messages.attachment.preview', $media->id), false);
        $this->assertNotNull($message->fresh()?->read_at, 'opening marks it read');
    }

    public function test_attachments_download_only_for_the_admin_and_never_inline(): void
    {
        $media = $this->report()->getFirstMedia(Message::ATTACHMENTS);
        $this->assertNotNull($media);
        $url = route('admin.messages.attachment', $media->id);

        $this->get($url)->assertRedirect(route('frontend.auth.login'));
        $this->actingAs($this->customer)->get($url)->assertForbidden();

        $response = $this->actingAs($this->admin)->get($url);
        $response->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('schermata-carrello.png', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_image_previews_are_shown_inline_to_the_admin_only_and_never_for_pdfs(): void
    {
        $message = $this->report();
        $image = $message->getFirstMedia(Message::ATTACHMENTS);
        $this->assertNotNull($image);
        $preview = route('admin.messages.attachment.preview', $image->id);

        $this->get($preview)->assertRedirect(route('frontend.auth.login'));
        $this->actingAs($this->customer)->get($preview)->assertForbidden();
        $response = $this->actingAs($this->admin)->get($preview);
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));

        $pdf = $message->addMediaFromString('%PDF-1.4
%%EOF
')->usingFileName('doc.pdf')->toMediaCollection(Message::ATTACHMENTS);
        $this->actingAs($this->admin)->get(route('admin.messages.attachment.preview', $pdf->id))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.messages.attachment', $pdf->id))->assertOk();
    }

    public function test_media_outside_the_attachments_collection_is_not_served(): void
    {
        $message = Message::query()->create(['email' => 'x@example.com', 'name' => 'X', 'subject' => 'S', 'message' => 'M']);
        $other = $message->addMediaFromString('not an attachment')->usingFileName('other.txt')->toMediaCollection('other', 'reports');

        $this->actingAs($this->admin)->get(route('admin.messages.attachment', $other->id))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.messages.attachment', 999999))->assertNotFound();
    }

    public function test_the_inbox_warns_when_public_reports_run_as_auth(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListMessages::class)->assertDontSee(__('admin.message.public_degraded'));

        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
        Livewire::test(ListMessages::class)->assertSee(__('admin.message.public_degraded'));
    }
}
