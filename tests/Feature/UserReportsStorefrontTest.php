<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CaptchaProvider;
use App\Contracts\MessageForwarder;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ForwardedReport;
use App\Support\Reports\ReportAccess;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** R5 of docs/06_USER_REPORTS.md: the report entry point on every storefront page. */
final class UserReportsStorefrontTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    private User $admin;

    /** @var list<ForwardedReport> */
    public static array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        self::$sent = [];
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
        $this->customer = User::query()->create(['name' => 'Cliente', 'email' => 'shop-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $this->customer->assignRole('customer');
        Customer::query()->create(['user_id' => $this->customer->id, 'email' => $this->customer->email, 'name' => 'Anna', 'surname' => 'Neri', 'customer_type' => 'Privato']);
        $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'shop-admin-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $this->admin->assignRole('admin');
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
                UserReportsStorefrontTest::$sent[] = $report;

                return 'H-'.count(UserReportsStorefrontTest::$sent);
            }
        });
        config(['mercatura.providers.message_forwarder' => 'fake']);
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
                return '<input type="hidden" id="'.$fieldId.'" name="captcha_token" value="ok">';
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

    public function test_auth_mode_shows_the_entry_point_to_logged_in_customers_only_and_mounts_one_dialog(): void
    {
        $this->get('/')->assertOk()->assertDontSee('reportDialog(', false)->assertDontSee(__('frontend.reports.open'));

        $home = $this->actingAs($this->customer)->get('/');
        $home->assertOk()->assertSee(__('frontend.reports.open'));
        $this->assertSame(1, substr_count((string) $home->getContent(), 'reportDialog('), 'one dialog per page');

        $account = $this->actingAs($this->customer)->get(route('frontend.auth.reserved_area'));
        $this->assertSame(1, substr_count((string) $account->getContent(), 'reportDialog('), 'the account page uses the layout dialog');
    }

    public function test_public_mode_without_a_captcha_behaves_as_auth(): void
    {
        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
        $this->get('/')->assertOk()->assertDontSee('reportDialog(', false);
    }

    public function test_public_mode_with_a_captcha_offers_guests_a_form_without_attachments(): void
    {
        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
        $this->acceptingCaptcha();

        $this->get('/')->assertOk()
            ->assertSee('reportDialog(', false)
            ->assertSee('id="report-email"', false)
            ->assertSee('name="website"', false)
            ->assertDontSee('id="report-attachments"', false);

        $this->postJson(route('frontend.reports.store'), ['kind' => 'question', 'title' => 'Spedizioni', 'description' => 'Spedite in Svizzera?', 'name' => 'Ospite', 'email' => 'ospite@example.com', 'captcha_token' => 'ok'])
            ->assertCreated()->assertJson(['message' => __('frontend.reports.sent_text_guest')]);
        $this->assertDatabaseHas('messages', ['email' => 'ospite@example.com', 'reporter_role' => 'guest', 'type' => 'report']);
    }

    public function test_an_admin_on_the_storefront_sees_nothing_without_a_forwarder(): void
    {
        $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee('reportDialog(', false);
        $this->actingAs($this->admin)->postJson(route('frontend.reports.store'), ['kind' => 'bug', 'title' => 't', 'description' => 'd'])->assertForbidden();
    }

    public function test_an_admin_report_from_the_storefront_goes_straight_to_the_forwarder(): void
    {
        $this->useFakeForwarder();
        $page = $this->actingAs($this->admin)->get('/');
        $page->assertOk()->assertSee('reportDialog(', false)->assertSee(__('frontend.reports.intro_admin', ['target' => 'Helpdesk']))
            ->assertSee('id="report-attachments"', false);

        $before = Message::query()->count();
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $this->actingAs($this->admin)->post(route('frontend.reports.store'), [
            'kind' => 'bug',
            'title' => 'Prezzo barrato sbagliato',
            'description' => 'Il cliente ha scritto da mario@example.com.',
            'page_url' => 'http://localhost/prodotti?sort=price',
            'attachments' => [UploadedFile::fake()->createWithContent('scheda.png', $png)],
        ], ['Accept' => 'application/json'])
            ->assertCreated()->assertJson(['message' => __('frontend.reports.forwarded_text', ['target' => 'Helpdesk', 'reference' => 'H-1'])]);

        $this->assertSame($before, Message::query()->count(), 'nothing stored in messages');
        $sent = self::$sent[0];
        $this->assertSame('admin', $sent->context['reporter_role']);
        $this->assertSame('storefront', $sent->context['area']);
        $this->assertSame('http://localhost/prodotti', $sent->pageUrl);
        $this->assertStringNotContainsString('mario@example.com', $sent->description);
        $this->assertSame(['scheda.png'], array_column($sent->attachments, 'name'));

        $this->actingAs($this->admin)->postJson(route('frontend.reports.store'), [
            'kind' => 'bug', 'title' => 't', 'description' => 'd',
            'attachments' => [UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')],
        ])->assertUnprocessable();
    }
}
