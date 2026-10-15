<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Messages\ForwardMessage;
use App\Actions\Messages\SendAdminReport;
use App\Actions\Messages\SubmitReport;
use App\Contracts\MessageForwarder;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Http\Livewire\Admin\ReportProblem;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ForwardedReport;
use App\Support\Reports\Redactor;
use App\Support\Reports\ReportAccess;
use App\Support\Reports\TechnicalBlock;
use Database\Seeders\CoreSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** R3 of docs/06_USER_REPORTS.md: forward contract, pseudonymised forward, the admin's own report. */
final class MessageForwardTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $customer;

    /** @var list<ForwardedReport> */
    public static array $sent = [];

    /** @var array<string, string> attachment contents read during forward() */
    public static array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        Storage::fake('local');
        self::$sent = [];
        self::$files = [];
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
        $this->admin = User::query()->create(['name' => 'Admin Rossi', 'email' => 'forward-admin@example.com', 'password' => Hash::make('password')]);
        $this->admin->assignRole('admin');
        $this->customer = User::query()->create(['name' => 'Cliente', 'email' => 'giulia.bianchi@example.com', 'password' => Hash::make('password')]);
        $this->customer->assignRole('customer');
        Customer::query()->create(['user_id' => $this->customer->id, 'email' => $this->customer->email, 'name' => 'Giulia', 'surname' => 'Bianchi', 'customer_type' => 'Privato']);
    }

    private function useFakeForwarder(): void
    {
        $this->app->bind('mercatura.message-forwarders.fake', fn (): MessageForwarder => new class implements MessageForwarder
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function label(): string
            {
                return 'Helpdesk';
            }

            public function forward(ForwardedReport $report): string
            {
                MessageForwardTest::$sent[] = $report;
                foreach ($report->attachments as $file) {
                    MessageForwardTest::$files[$file['name']] = (string) file_get_contents($file['path']);
                }

                return 'T-'.count(MessageForwardTest::$sent);
            }
        });
        config(['mercatura.providers.message_forwarder' => 'fake']);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function report(): Message
    {
        $request = Request::create('http://localhost/segnala', 'POST', server: ['REMOTE_ADDR' => '10.0.0.3', 'HTTP_USER_AGENT' => 'ForwardTest/1.0']);
        $this->app->instance('request', $request);
        $message = app(SubmitReport::class)->handle(
            ['kind' => 'bug', 'title' => 'Ordine bloccato, chiamatemi al 333 1234567', 'description' => "Scrivete a giulia.bianchi@example.com, IBAN IT60X0542811101000000123456.\nL'ordine non avanza.", 'page_url' => 'http://localhost/ordini?q=giulia', 'viewport' => '390x844', 'js_errors' => ['Error at giulia.bianchi@example.com']],
            [UploadedFile::fake()->createWithContent('indirizzo cliente.png', $this->png()), UploadedFile::fake()->createWithContent('log.pdf', "%PDF-1.4\n%%EOF\n")],
            $this->customer,
            $request,
        );
        $this->assertNotNull($message);

        return $message;
    }

    public function test_with_the_null_driver_nothing_shows(): void
    {
        $message = $this->report();
        $this->actingAs($this->admin);

        $page = Livewire::test(ViewMessage::class, ['record' => $message->id]);
        $page->assertOk();
        $page->assertActionHidden('forward');
        $this->assertFalse(ReportProblem::shown());
        $this->get('/admin')->assertOk()->assertDontSee(__('admin.message.report.action'));
    }

    public function test_an_unknown_forwarder_name_behaves_as_null(): void
    {
        config(['mercatura.providers.message_forwarder' => 'missing']);
        $this->assertFalse(app(MessageForwarder::class)->isAvailable());
    }

    public function test_the_forward_is_pseudonymised_and_sends_only_the_chosen_attachments(): void
    {
        $this->useFakeForwarder();
        $message = $this->report();
        $pdf = $message->getMedia(Message::ATTACHMENTS)->firstWhere('mime_type', 'application/pdf');
        $this->assertNotNull($pdf);
        $this->actingAs($this->admin);

        $page = Livewire::test(ViewMessage::class, ['record' => $message->id]);
        $page->assertActionVisible('forward');
        $image = $message->getMedia(Message::ATTACHMENTS)->firstWhere('mime_type', 'image/png');
        $this->assertNotNull($image);
        $page->mountAction('forward');
        $preview = route('admin.messages.attachment.preview', $image->id);
        $page->assertSchemaComponentExists('attachments', checkComponentUsing: fn ($field): bool => $field instanceof \Filament\Forms\Components\CheckboxList
            && $field->isHtmlAllowed()
            && str_contains((string) ($field->getOptions()[$image->id] ?? ''), $preview));
        $page->unmountAction();
        $page->callAction('forward', data: ['attachments' => [$pdf->id]]);
        $page->assertHasNoActionErrors();

        $this->assertCount(1, self::$sent);
        $sent = self::$sent[0];
        $this->assertSame(['name' => 'Admin Rossi', 'email' => 'forward-admin@example.com'], $sent->reporter);
        $outgoing = json_encode([$sent->title, $sent->description, $sent->pageUrl, $sent->context, $sent->technical, array_column($sent->attachments, 'name')], JSON_UNESCAPED_UNICODE);
        foreach (['giulia.bianchi@example.com', 'Giulia', 'Bianchi', '333 1234567', 'IT60X0542811101000000123456', '10.0.0.3', 'q=giulia'] as $personal) {
            $this->assertStringNotContainsString($personal, (string) $outgoing, "{$personal} must not leave the platform");
        }
        $this->assertArrayNotHasKey('user_id', $sent->context);
        $this->assertSame('messages#'.$message->id, $sent->context['reference']);
        $this->assertSame('customer', $sent->context['reporter_role']);
        $this->assertSame('http://localhost/ordini', $sent->pageUrl);
        $this->assertStringContainsString('segnalazione #'.$message->id.' (Cliente)', $sent->technical);
        $this->assertStringContainsString('Area riservata', $sent->technical);
        $this->assertSame(['log.pdf'], array_column($sent->attachments, 'name'), 'only the checked attachment');
        $this->assertSame("%PDF-1.4\n%%EOF\n", self::$files['log.pdf']);

        $message->refresh();
        $this->assertNotNull($message->forwarded_at);
        $this->assertSame($this->admin->id, $message->forwarded_by);
        $this->assertSame('T-1', $message->forward_reference);
        $this->assertSame('Ordine bloccato, chiamatemi al 333 1234567', $message->subject, 'the original message is untouched');

        $again = Livewire::test(ViewMessage::class, ['record' => $message->id]);
        $again->assertActionHidden('forward');
        $this->expectException(AuthorizationException::class);
        app(ForwardMessage::class)->handle($message, $this->admin, 'x', 'y', []);
    }

    public function test_contact_messages_and_users_without_the_permission_cannot_forward(): void
    {
        $this->useFakeForwarder();
        $contact = Message::query()->create(['email' => 'altro@example.com', 'name' => 'Altro', 'subject' => 'Info', 'message' => 'Contatto.']);
        $this->assertFalse($this->admin->can('forward', $contact));

        $clerk = User::query()->create(['name' => 'Impiegato', 'email' => 'clerk@example.com', 'password' => Hash::make('password')]);
        $clerk->givePermissionTo('orders.manage');
        $this->assertFalse($clerk->can('forward', $this->report()));
    }

    public function test_the_admin_report_goes_straight_to_the_forwarder_redacted_and_stores_nothing(): void
    {
        $this->useFakeForwarder();
        $this->actingAs($this->admin);
        $this->assertTrue(ReportProblem::shown());
        $this->get('/admin')->assertOk()->assertSee(__('admin.message.report.action'));

        $before = Message::query()->count();
        Storage::disk('local')->put('tmp/report-files/a.png', $this->png());
        $request = Request::create('http://localhost/admin', 'POST', server: ['REMOTE_ADDR' => '10.0.0.4', 'HTTP_USER_AGENT' => 'PanelBrowser/2.0']);
        $reference = app(SendAdminReport::class)->handle($this->admin, [
            'kind' => 'bug',
            'title' => 'Il cliente mario@example.com non vede gli ordini',
            'description' => 'Ha chiamato dal +39 06 1234 5678.',
            'page_url' => 'http://localhost/admin/messages?tableSearch=mario',
        ], [[Storage::disk('local')->path('tmp/report-files/a.png'), 'schermata pannello.png']], $request);

        $this->assertSame('T-1', $reference);
        $this->assertSame($before, Message::query()->count(), 'nothing stored in messages');
        $sent = self::$sent[0];
        $this->assertSame('Il cliente [oscurato] non vede gli ordini', $sent->title);
        $this->assertStringNotContainsString('1234 5678', $sent->description);
        $this->assertSame('admin', $sent->context['reporter_role']);
        $this->assertSame('admin', $sent->context['area']);
        $this->assertSame('http://localhost/admin/messages', $sent->pageUrl);
        $this->assertSame('PanelBrowser/2.0', $sent->context['user_agent']);
        $this->assertSame(['schermata pannello.png'], array_column($sent->attachments, 'name'));
        $this->assertNotFalse(imagecreatefromstring(self::$files['schermata pannello.png']), 're-encoded image');
    }

    public function test_the_top_bar_action_sends_the_report(): void
    {
        $this->useFakeForwarder();
        $this->actingAs($this->admin);

        $component = Livewire::test(ReportProblem::class);
        $component->callAction('report', data: ['kind' => 'question', 'title' => 'Come si esporta?', 'description' => 'Non trovo il pulsante.']);
        $this->assertCount(1, self::$sent);
        $this->assertSame('question', self::$sent[0]->kind->value);
        $this->assertStringContainsString('amministratore', self::$sent[0]->technical);
    }

    public function test_only_an_admin_with_the_permission_reports_directly(): void
    {
        $this->useFakeForwarder();
        $this->expectException(AuthorizationException::class);
        app(SendAdminReport::class)->handle($this->customer, ['kind' => 'bug', 'title' => 't', 'description' => 'd'], [], Request::create('http://localhost/'));
    }

    public function test_redactor_and_page_helpers(): void
    {
        $this->assertSame('Scrivi a [oscurato] o al [oscurato]', Redactor::text('Scrivi a a.b@example.it o al +39 333 123 4567'));
        $this->assertSame('IBAN [oscurato]', Redactor::text('IBAN IT60 X054 2811 1010 0000 0123 456'));
        $this->assertSame('Fisso [oscurato].', Redactor::text('Fisso 06.1234.5678.'));
        foreach (['Ordine 26701 del 15/10/2026', 'Variante #2670114', 'Vedi /prodotti/agenda-26701/2670114', 'Quantità 1000 pezzi'] as $kept) {
            $this->assertSame($kept, Redactor::text($kept));
        }
        $this->assertSame('https://shop.test/ordini', TechnicalBlock::pageWithoutQuery('https://shop.test/ordini?email=a@b.it#x'));
        $this->assertNull(TechnicalBlock::pageWithoutQuery(null));
    }
}
