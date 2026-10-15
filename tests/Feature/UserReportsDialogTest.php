<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Livewire\FrontendContactsTable;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\ReportAccess;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 of docs/06_USER_REPORTS.md: the report dialog in the account area and its JSON endpoint. */
final class UserReportsDialogTest extends TestCase
{
    use DatabaseTransactions;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        Storage::fake('reports');
        config(['mercatura.reports.access' => ReportAccess::AUTH]);
        $this->customer = User::query()->create(['name' => 'Cliente', 'email' => 'dialog-'.Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('password')]);
        $this->customer->assignRole('customer');
        Customer::query()->create(['user_id' => $this->customer->id, 'email' => $this->customer->email, 'name' => 'Anna', 'surname' => 'Verdi', 'customer_type' => 'Privato']);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_the_account_page_offers_the_dialog_only_when_reports_are_on(): void
    {
        $page = $this->actingAs($this->customer)->get(route('frontend.auth.reserved_area'));
        $page->assertOk()
            ->assertSee(__('frontend.reports.open'))
            ->assertSee(route('frontend.contacts.list'), false)
            ->assertSee('reportDialog(', false)
            ->assertSee('segnalazioni', false)
            ->assertSee('name="attachments[]"', false)
            ->assertDontSee('id="report-email"', false);

        config(['mercatura.reports.access' => ReportAccess::OFF]);
        $this->actingAs($this->customer)->get(route('frontend.auth.reserved_area'))
            ->assertOk()->assertDontSee('reportDialog(', false)->assertDontSee(__('frontend.reports.open'));
    }

    public function test_a_customer_sends_a_report_with_attachments_and_finds_it_among_the_messages(): void
    {
        $response = $this->actingAs($this->customer)->post(route('frontend.reports.store'), [
            'kind' => 'bug',
            'title' => 'Il pulsante ordina non risponde',
            'description' => 'Clicco e non succede nulla.',
            'page_url' => 'http://localhost/ordini',
            'viewport' => '390x844',
            'js_errors' => ['TypeError: x is null (app.js:1)'],
            'attachments' => [UploadedFile::fake()->createWithContent('schermata.png', $this->png())],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJson(['message' => __('frontend.reports.sent_text')]);
        $message = Message::query()->where('user_id', $this->customer->id)->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('Il pulsante ordina non risponde', $message->subject);
        $this->assertSame('account', $message->context['area']);
        $this->assertCount(1, $message->getMedia(Message::ATTACHMENTS));

        $this->actingAs($this->customer);
        Livewire::test(FrontendContactsTable::class)->assertSee(__('frontend.reports.types.report'))->assertSee('Il pulsante ordina non risponde');
    }

    public function test_the_endpoint_answers_in_json_for_refusals_and_errors(): void
    {
        $this->postJson(route('frontend.reports.store'), ['kind' => 'bug', 'title' => 't', 'description' => 'd', 'name' => 'Ospite', 'email' => 'o@example.com'])
            ->assertForbidden();

        $this->actingAs($this->customer)->postJson(route('frontend.reports.store'), ['kind' => 'bug', 'description' => 'manca il titolo'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title']);

        $this->actingAs($this->customer)->postJson(route('frontend.reports.store'), [
            'kind' => 'bug', 'title' => 't', 'description' => 'd',
            'attachments' => [UploadedFile::fake()->createWithContent('script.png', '<?php echo 1;')],
        ])->assertUnprocessable();

        config(['mercatura.reports.limits.user_per_minute' => 0]);
        $this->actingAs($this->customer)->postJson(route('frontend.reports.store'), ['kind' => 'bug', 'title' => 't', 'description' => 'd'])
            ->assertStatus(429);
    }
}
