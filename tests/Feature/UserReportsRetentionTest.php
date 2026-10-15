<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\ReporterRole;
use App\Enums\ReportKind;
use App\Models\Message;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R7 of docs/06_USER_REPORTS.md: report attachments are deleted after the retention period, the text stays. */
final class UserReportsRetentionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('reports');
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    }

    private function report(int $daysAgo, MessageType $type = MessageType::Report): Message
    {
        $message = Message::query()->create([
            'type' => $type, 'reporter_role' => ReporterRole::Customer, 'kind' => $type === MessageType::Report ? ReportKind::Bug : null,
            'email' => 'retention@example.test', 'name' => 'R', 'subject' => 'Segnalazione di '.$daysAgo.' giorni fa', 'message' => 'Testo che resta.',
        ]);
        $message->created_at = now()->subDays($daysAgo);
        $message->save();
        $message->addMediaFromString('png-bytes')->usingFileName('a'.$daysAgo.'.png')->toMediaCollection(Message::ATTACHMENTS);

        return $message;
    }

    public function test_old_attachments_go_recent_ones_and_the_text_stay(): void
    {
        config(['mercatura.reports.attachment_retention_days' => 90]);
        $old = $this->report(91);
        $recent = $this->report(89);
        $oldPath = $old->getFirstMedia(Message::ATTACHMENTS)?->getPathRelativeToRoot();
        $this->assertNotNull($oldPath);

        $this->artisan('cleanup:report-attachments', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(1, $old->fresh()?->getMedia(Message::ATTACHMENTS) ?? [], 'dry run deletes nothing');

        $this->artisan('cleanup:report-attachments')->expectsOutputToContain('1 files of 1 reports')->assertSuccessful();
        $this->assertCount(0, $old->fresh()?->getMedia(Message::ATTACHMENTS) ?? [1]);
        Storage::disk('reports')->assertMissing($oldPath);
        $this->assertSame('Testo che resta.', $old->fresh()?->message);
        $this->assertCount(1, $recent->fresh()?->getMedia(Message::ATTACHMENTS) ?? []);
    }

    public function test_zero_keeps_everything_and_the_option_overrides_the_config(): void
    {
        $old = $this->report(400);
        config(['mercatura.reports.attachment_retention_days' => 0]);
        $this->artisan('cleanup:report-attachments')->expectsOutputToContain('retention off')->assertSuccessful();
        $this->assertCount(1, $old->fresh()?->getMedia(Message::ATTACHMENTS) ?? []);

        $this->artisan('cleanup:report-attachments', ['--days' => 365])->assertSuccessful();
        $this->assertCount(0, $old->fresh()?->getMedia(Message::ATTACHMENTS) ?? [1]);
    }

    public function test_the_command_runs_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('cleanup:report-attachments')->assertSuccessful();
    }
}
