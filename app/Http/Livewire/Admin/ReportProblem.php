<?php

declare(strict_types=1);

namespace App\Http\Livewire\Admin;

use App\Actions\Messages\SendAdminReport;
use App\Contracts\MessageForwarder;
use App\Enums\ReportKind;
use App\Models\User;
use App\Support\Reports\ReportAttachments;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

/**
 * "Segnala un problema" in the panel's top bar (docs/06_USER_REPORTS.md §2.9, R3):
 * an admin's report goes straight to the installation's helpdesk, nothing is
 * stored. Rendered only when a forwarder is available and the admin holds
 * "messages.forward" (AdminPanelProvider render hook).
 */
final class ReportProblem extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** The panel page the admin is on, captured when the top bar renders. */
    public string $pageUrl = '';

    public function mount(): void
    {
        $this->pageUrl = request()->fullUrl();
    }

    public static function shown(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('messages.forward') && app(MessageForwarder::class)->isAvailable();
    }

    public function reportAction(): Action
    {
        $target = app(MessageForwarder::class)->label();

        return Action::make('report')
            ->label(__('admin.message.report.action'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('gray')
            ->size('sm')
            ->modalHeading(__('admin.message.report.heading', ['target' => $target]))
            ->modalDescription(__('admin.message.report.intro', ['target' => $target]))
            ->modalSubmitActionLabel(__('admin.message.forward.submit'))
            ->schema([
                Select::make('kind')->label(__('admin.message.kind'))->options(ReportKind::options())->default(ReportKind::Bug->value)->required()->native(false),
                TextInput::make('title')->label(__('admin.message.subject'))->required()->maxLength((int) config('mercatura.reports.title_max')),
                Textarea::make('description')->label(__('admin.message.message'))->required()->rows(6)->maxLength((int) config('mercatura.reports.description_max')),
                FileUpload::make('attachments')->label(__('admin.message.attachments'))
                    ->multiple()->maxFiles((int) config('mercatura.reports.max_files'))->maxSize((int) config('mercatura.reports.max_file_kb'))
                    ->acceptedFileTypes(ReportAttachments::MIMETYPES)
                    ->disk('local')->directory('tmp/report-files')->visibility('private')
                    ->storeFileNamesIn('attachment_names')
                    ->helperText(__('admin.message.report.attachments_hint')),
                SchemaView::make('filament.reports.capture')->key('capture'),
            ])
            ->action(function (array $data) use ($target): void {
                $admin = Auth::user();
                if (! $admin instanceof User) {
                    return;
                }
                $names = (array) ($data['attachment_names'] ?? []);
                $stored = array_values(array_map('strval', (array) ($data['attachments'] ?? [])));
                $files = array_map(static fn (string $p): array => [Storage::disk('local')->path($p), (string) ($names[$p] ?? basename($p))], $stored);
                try {
                    $reference = app(SendAdminReport::class)->handle($admin, [
                        'kind' => $data['kind'] ?? null,
                        'title' => $data['title'] ?? null,
                        'description' => $data['description'] ?? null,
                        'page_url' => $this->pageUrl,
                    ], $files, request());
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first() ?? __('admin.message.report.failed', ['target' => $target]))->danger()->send();

                    return;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title(__('admin.message.report.failed', ['target' => $target]))->danger()->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($stored);
                }
                Notification::make()->title(__('admin.message.report.done', ['target' => $target, 'reference' => $reference]))->success()->send();
            });
    }

    public function render(): View
    {
        return view('livewire.admin-report-problem');
    }
}
