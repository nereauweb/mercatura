<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messages\SubmitReport;
use App\Contracts\CaptchaProvider;
use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ReportAccess;
use Database\Seeders\CoreSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** R1 of docs/06_USER_REPORTS.md: reports as messages rows, access modes, attachments, limits. */
final class UserReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
    }

    private function customer(): User
    {
        $user = User::factory()->create(['name' => 'account name', 'email' => 'report-'.Str::lower(Str::random(16)).'@example.test']);
        $user->assignRole('customer');
        Customer::query()->create(['user_id' => $user->id, 'email' => $user->email, 'name' => 'Anna', 'surname' => 'Rossi', 'customer_type' => 'Privato']);

        return $user;
    }

    private function request(string $ip = '10.0.0.1'): Request
    {
        $request = Request::create('http://localhost/segnala', 'POST', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'TestBrowser/1.0']);
        $this->app->instance('request', $request);

        return $request;
    }

    /** @return array<string, mixed> */
    private function input(array $extra = []): array
    {
        return ['kind' => 'bug', 'title' => 'Il carrello non si aggiorna', 'description' => 'Cambiando quantità il totale resta uguale.', ...$extra];
    }

    /** @param  array<int, UploadedFile>  $files */
    private function submit(array $input, array $files = [], ?User $user = null): ?Message
    {
        return app(SubmitReport::class)->handle($input, $files, $user, $this->request());
    }

    private function acceptingCaptcha(): void
    {
        $this->app->instance(CaptchaProvider::class, new class implements CaptchaProvider
        {
            public function key(): string
            {
                return 'fake';
            }

            public function enabled(): bool
            {
                return true;
            }

            public function responseField(): string
            {
                return 'captcha_token';
            }

            public function renderField(string $fieldId, string $action): string
            {
                return '';
            }

            public function renderScript(): string
            {
                return '';
            }

            public function verify(string $action, mixed $token, ?string $ip = null): bool
            {
                return $token === 'ok';
            }
        });
    }

    private function png(string $trailer = ''): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        return $bytes.$trailer;
    }

    public function test_a_customer_report_takes_identity_role_and_area_from_the_server(): void
    {
        $user = $this->customer();
        $message = $this->submit($this->input(['page_url' => 'http://localhost/ordini', 'viewport' => '390x844', 'js_errors' => ['TypeError: x is undefined']]), [], $user);

        $this->assertNotNull($message);
        $message->refresh();
        $this->assertSame(MessageType::Report, $message->type);
        $this->assertSame(ReporterRole::Customer, $message->reporter_role);
        $this->assertSame(ReportKind::Bug, $message->kind);
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame([$user->email, 'Anna', 'Rossi'], [$message->email, $message->name, $message->surname]);
        $this->assertSame('Il carrello non si aggiorna', $message->subject);
        $this->assertSame('http://localhost/ordini', $message->page_url);
        $this->assertSame('account', $message->context['area']);
        $this->assertSame('frontend.auth.order.list', $message->context['route']);
        $this->assertSame('390x844', $message->context['viewport']);
        $this->assertSame('TestBrowser/1.0', $message->context['user_agent']);
        $this->assertSame(['TypeError: x is undefined'], $message->context['js_errors']);
    }

    public function test_a_logged_in_user_cannot_type_another_identity_and_foreign_pages_are_dropped(): void
    {
        $user = $this->customer();
        try {
            $this->submit($this->input(['email' => 'boss@example.com']), [], $user);
            $this->fail('email must be prohibited for a logged-in sender');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        $message = $this->submit($this->input(['page_url' => 'https://evil.example/phish']), [], $user);
        $this->assertNull($message?->page_url);
        $this->assertSame('storefront', $message?->context['area']);
    }

    public function test_access_modes_and_the_captcha_fallback(): void
    {
        $access = app(ReportAccess::class);
        $admin = User::factory()->create(['email' => 'report-admin-'.Str::lower(Str::random(16)).'@example.test']);
        $admin->assignRole('admin');

        $this->assertFalse($access->allows(null), 'auth: guests refused');
        $this->assertTrue($access->allows($this->customer()));
        $this->assertFalse($access->allows($admin), 'admins report through the forwarder');

        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
        $this->assertSame(ReportAccess::AUTH, $access->effective(), 'public without a captcha behaves as auth');
        $this->assertTrue($access->degraded());
        $this->assertFalse($access->allows(null));

        $this->acceptingCaptcha();
        $this->assertSame(ReportAccess::PUBLIC, $access->effective());
        $this->assertFalse($access->degraded());
        $this->assertTrue($access->allows(null));
        $this->assertFalse($access->canAttach(null), 'guests never attach');

        config(['mercatura.reports.access' => ReportAccess::OFF]);
        $this->assertFalse($access->allows($this->customer()));
        $this->expectException(AuthorizationException::class);
        $this->submit($this->input(), [], $admin);
    }

    public function test_a_guest_report_in_public_mode_needs_captcha_and_never_attaches(): void
    {
        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
        $this->acceptingCaptcha();
        $guest = $this->input(['name' => 'Mario', 'email' => 'mario@example.com', 'captcha_token' => 'ok']);

        $message = $this->submit($guest);
        $this->assertNotNull($message);
        $this->assertSame(ReporterRole::Guest, $message->reporter_role);
        $this->assertNull($message->user_id);
        $this->assertSame('mario@example.com', $message->email);

        $this->assertNull($this->submit([...$guest, SubmitReport::HONEYPOT => 'http://spam']), 'honeypot: silently dropped');

        try {
            $this->submit([...$guest, 'captcha_token' => 'bad']);
            $this->fail('a wrong captcha must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('captcha_token', $e->errors());
        }

        try {
            $this->submit($guest, [UploadedFile::fake()->createWithContent('shot.png', $this->png())]);
            $this->fail('guests never attach');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attachments', $e->errors());
        }
    }

    public function test_a_guest_is_refused_in_auth_mode(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->submit($this->input(['name' => 'Mario', 'email' => 'mario@example.com']));
    }

    public function test_a_script_renamed_as_an_image_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->submit($this->input(), [UploadedFile::fake()->createWithContent('shot.png', "<?php system(\$_GET['c']);")], $this->customer());
    }

    public function test_an_image_that_does_not_decode_is_refused(): void
    {
        $broken = substr($this->png(), 0, 40).str_repeat("\0", 64);
        try {
            $this->submit($this->input(), [UploadedFile::fake()->createWithContent('shot.png', $broken)], $this->customer());
            $this->fail('a broken image must be refused');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertSame(0, Message::query()->where('type', 'report')->count(), 'nothing stored');
    }

    public function test_images_are_reencoded_and_stored_privately_pdfs_kept(): void
    {
        $marker = '<?php /* HIDDEN-PAYLOAD */ ?>';
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        $message = $this->submit($this->input(), [
            UploadedFile::fake()->createWithContent('schermata cliente.png', $this->png($marker)),
            UploadedFile::fake()->createWithContent('ordine.pdf', $pdf),
        ], $this->customer());

        $media = $message?->getMedia(Message::ATTACHMENTS);
        $this->assertCount(2, $media);
        foreach ($media as $item) {
            $this->assertSame('reports', $item->disk);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}\.(png|pdf)$/', $item->file_name, 'stored names are generated');
        }
        $png = $media->firstWhere('mime_type', 'image/png');
        $this->assertSame('schermata cliente.png', $png?->name);
        $stored = Storage::disk('reports')->get($png->getPathRelativeToRoot());
        $this->assertStringNotContainsString('HIDDEN-PAYLOAD', (string) $stored, 'the payload after the image does not survive');
        $this->assertNotFalse(imagecreatefromstring((string) $stored));
        $this->assertSame($pdf, Storage::disk('reports')->get($media->firstWhere('mime_type', 'application/pdf')->getPathRelativeToRoot()));
    }

    public function test_rate_and_upload_limits(): void
    {
        config(['mercatura.reports.limits.user_per_minute' => 2]);
        $user = $this->customer();
        $this->submit($this->input(), [], $user);
        $this->submit($this->input(), [], $user);
        try {
            $this->submit($this->input(), [], $user);
            $this->fail('third report in a minute must be throttled');
        } catch (ThrottleRequestsException) {
            $this->addToAssertionCount(1);
        }

        config(['mercatura.reports.limits.user_mb_per_day' => 0]);
        $this->expectException(ThrottleRequestsException::class);
        $this->submit($this->input(), [UploadedFile::fake()->createWithContent('a.png', $this->png())], $this->customer());
    }

    public function test_the_account_pages_match_messages_by_user_id_not_by_email(): void
    {
        $user = $this->customer();
        $byEmail = Message::query()->create(['email' => $user->email, 'name' => 'Anna', 'subject' => 'Vecchio', 'message' => 'Inviato da chiunque con questa email.']);
        $linked = Message::query()->create(['user_id' => $user->id, 'email' => $user->email, 'name' => 'Anna', 'subject' => 'Mio', 'message' => 'Collegato al mio account.']);

        $this->actingAs($user)->get('/messaggi/'.$linked->id)->assertOk()->assertSee('Collegato al mio account.');
        $this->actingAs($user)->get('/messaggi/'.$byEmail->id)->assertNotFound();
    }

    public function test_the_contact_form_links_the_message_to_the_logged_in_sender(): void
    {
        $user = $this->customer();
        $this->actingAs($user)->post('/contattaci/invia', ['name' => 'Anna', 'surname' => 'Rossi', 'email' => $user->email, 'subject' => 'Info', 'message' => 'Da loggata.', 'consent_gdpr' => '1'])->assertOk();
        $this->assertDatabaseHas('messages', ['subject' => 'Info', 'user_id' => $user->id, 'reporter_role' => 'customer', 'type' => 'contact']);
    }
}
