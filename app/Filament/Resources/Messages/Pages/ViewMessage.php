<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Pages;

use App\Actions\Messages\ForwardMessage;
use App\Contracts\MessageForwarder;
use App\Filament\Resources\Messages\AttachmentPreview;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Support\Reports\Redactor;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    /** Opening a message marks it read, as the legacy admin did. */
    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);
        if ($record instanceof Message && $record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [$this->forwardAction()];
    }

    /**
     * Forward of a report to the installation's helpdesk (docs/06_USER_REPORTS.md §6):
     * the admin reviews the redacted text and picks the attachments (none by default).
     */
    private function forwardAction(): Action
    {
        $forwarder = app(MessageForwarder::class);

        return Action::make('forward')
            ->label(fn (): string => __('admin.message.forward.action', ['target' => $forwarder->label()]))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Message $record): bool => $forwarder->isAvailable() && (Auth::user()?->can('forward', $record) ?? false))
            ->modalHeading(fn (): string => __('admin.message.forward.heading', ['target' => $forwarder->label()]))
            ->modalDescription(__('admin.message.forward.intro'))
            ->modalSubmitActionLabel(__('admin.message.forward.submit'))
            ->fillForm(fn (Message $record): array => [
                'title' => Redactor::text((string) $record->subject),
                'description' => Redactor::text((string) $record->message),
                'attachments' => [],
            ])
            ->schema(fn (Message $record): array => [
                TextInput::make('title')->label(__('admin.message.subject'))->required()->maxLength((int) config('mercatura.reports.title_max')),
                Textarea::make('description')->label(__('admin.message.message'))->required()->rows(8)->maxLength((int) config('mercatura.reports.description_max')),
                CheckboxList::make('attachments')->label(__('admin.message.attachments'))
                    // Thumbnails, so the admin sees what each box authorises (labels escaped by AttachmentPreview).
                    ->options($record->getMedia(Message::ATTACHMENTS)->mapWithKeys(fn ($media): array => [$media->id => AttachmentPreview::html($media, download: false)])->all())
                    ->allowHtml()
                    ->helperText(__('admin.message.forward.attachments_hint'))
                    ->visible($record->getMedia(Message::ATTACHMENTS)->isNotEmpty()),
                Text::make(__('admin.message.forward.reference_hint', ['id' => $record->id])),
            ])
            ->action(function (array $data, Message $record) use ($forwarder): void {
                $admin = Auth::user();
                if (! $admin instanceof User) {
                    return;
                }
                try {
                    $reference = app(ForwardMessage::class)->handle($record, $admin, (string) $data['title'], (string) $data['description'], array_map('intval', (array) ($data['attachments'] ?? [])));
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title(__('admin.message.forward.failed', ['target' => $forwarder->label()]))->danger()->send();

                    return;
                }
                Notification::make()->title(__('admin.message.forward.done', ['target' => $forwarder->label(), 'reference' => $reference]))->success()->send();
            });
    }
}
