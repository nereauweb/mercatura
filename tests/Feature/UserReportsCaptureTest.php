<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CaptchaProvider;
use App\Contracts\MessageForwarder;
use App\Http\Livewire\Admin\ReportProblem;
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
use Livewire\Livewire;
use Tests\TestCase;

/** R6 of docs/06_USER_REPORTS.md: screen capture with recorded consent. */
final class UserReportsCaptureTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
        $this->customer = User::query()->create(['name' => 'Cliente', 'email' => 'capture-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $this->customer->assignRole('customer');
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
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
                return 'H-1';
            }
        });
        config(['mercatura.providers.message_forwarder' => 'fake']);
    }

    public function test_the_consent_is_recorded_with_its_text_version_only_when_a_file_comes_with_it(): void
    {
        $this->actingAs($this->customer)->post(route('frontend.reports.store'), [
            'kind' => 'bug', 'title' => 'Con schermata', 'description' => 'd', 'screenshot_consent' => '1',
            'attachments' => [UploadedFile::fake()->createWithContent('schermata-20261015.png', $this->png())],
        ], ['Accept' => 'application/json'])->assertCreated();
        $with = Message::query()->where('subject', 'Con schermata')->firstOrFail();
        $this->assertNotNull($with->screenshot_consent_at);
        $this->assertSame(config('mercatura.reports.screenshot_consent_version'), $with->screenshot_consent_version);

        $this->actingAs($this->customer)->postJson(route('frontend.reports.store'), ['kind' => 'bug', 'title' => 'Senza file', 'description' => 'd', 'screenshot_consent' => '1'])->assertCreated();
        $this->assertNull(Message::query()->where('subject', 'Senza file')->firstOrFail()->screenshot_consent_at);

        $this->actingAs($this->customer)->post(route('frontend.reports.store'), [
            'kind' => 'bug', 'title' => 'Senza consenso', 'description' => 'd',
            'attachments' => [UploadedFile::fake()->createWithContent('foto.png', $this->png())],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertNull(Message::query()->where('subject', 'Senza consenso')->firstOrFail()->screenshot_consent_at);
    }

    public function test_customers_get_the_consent_box_admins_a_warning_guests_nothing(): void
    {
        $this->actingAs($this->customer)->get('/')->assertOk()
            ->assertSee(__('frontend.reports.capture.consent'))
            ->assertSee('takeCapture()', false)
            ->assertSee('captureBar', false);

        config(['mercatura.reports.access' => ReportAccess::PUBLIC]);
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
                return true;
            }
        });
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('reportDialog(', false)->assertDontSee('takeCapture()', false);

        $this->useFakeForwarder();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'capture-admin-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee(__('frontend.reports.capture.admin_hint'))
            ->assertDontSee(__('frontend.reports.capture.consent'));
    }

    public function test_the_panel_report_offers_the_capture_and_loads_its_script(): void
    {
        $this->useFakeForwarder();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'capture-panel-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get('/admin')->assertOk()->assertSee('report-capture', false)->assertSee('mercatura-capturing', false);
        $component = Livewire::test(ReportProblem::class);
        $component->mountAction('report');
        $component->assertSchemaComponentExists('capture');
        $this->assertStringContainsString(__('admin.message.report.capture'), view('filament.reports.capture')->render());
    }
}
