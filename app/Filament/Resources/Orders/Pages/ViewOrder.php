<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return __('admin.order.number', ['id' => $this->getRecord()->getKey()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->transitionAction(),
            $this->resendAction(),
            $this->uploadFileAction(),
            $this->deleteFileAction(),
        ];
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->getRecord()) ?? false;
    }

    /** The legacy status form: status, payment status, payment method (draft/requested only), tracking. */
    private function transitionAction(): Action
    {
        return Action::make('transition')
            ->label(__('admin.order.actions.transition'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => $this->canManage())
            ->fillForm(fn (Order $record): array => [
                'status' => $record->status,
                'payment_status' => $record->payment_status,
                'payment_method' => $record->payment_method,
                'tracking_code' => $record->tracking_code,
            ])
            ->schema([
                Select::make('status')->label(__('admin.order.status'))->options(OrderStatus::options())->required()->native(false),
                Select::make('payment_status')->label(__('admin.order.payment_status'))->options(PaymentStatus::options())->required()->native(false),
                Select::make('payment_method')->label(__('admin.order.payment_method'))->options(PaymentMethod::options())->native(false)
                    ->disabled(fn (Order $record): bool => ! in_array($record->status, [OrderStatus::Draft->value, OrderStatus::Requested->value], true))
                    ->helperText(fn (Order $record): ?string => in_array($record->status, [OrderStatus::Draft->value, OrderStatus::Requested->value], true) ? null : __('admin.order.actions.payment_method_locked')),
                TextInput::make('tracking_code')->label(__('admin.order.tracking_code'))->maxLength(128),
            ])
            ->action(function (array $data, Order $record): void {
                $sent = app(TransitionOrder::class)->handle($record, $data);
                $names = array_map(fn (string $key): string => __('admin.order.mail_names.'.$key), $sent);
                Notification::make()
                    ->title(__('admin.order.actions.transition_done'))
                    ->body($sent === [] ? __('admin.order.actions.transition_no_mail') : __('admin.order.actions.transition_mails', ['list' => implode(', ', $names)]))
                    ->success()->send();
                $this->refreshFormData(['status', 'payment_status', 'payment_method', 'tracking_code']);
            });
    }

    private function resendAction(): Action
    {
        return Action::make('resendConfirmation')
            ->label(__('admin.order.actions.resend'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage())
            ->requiresConfirmation()
            ->modalDescription(__('admin.order.actions.resend_confirm'))
            ->action(function (Order $record): void {
                $record->send_notification('stored', 'user');
                Notification::make()->title(__('admin.order.actions.resend_done'))->success()->send();
            });
    }

    /** Legacy accepted types: jpeg, jpg, png, svg, webp, pdf, ai, eps up to 10 MB, collection order_files. */
    private function uploadFileAction(): Action
    {
        return Action::make('uploadFile')
            ->label(__('admin.order.actions.upload_file'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage())
            ->schema([
                FileUpload::make('file')->label(__('admin.order.actions.file'))->required()->preserveFilenames()
                    ->disk('local')->directory('tmp/order-files')->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/postscript', 'application/illustrator', 'application/eps', 'image/x-eps'])
                    ->maxSize(10240)
                    ->helperText(__('admin.order.actions.upload_hint')),
            ])
            ->action(function (array $data, Order $record): void {
                $path = Storage::disk('local')->path((string) $data['file']);
                $record->addMedia($path)->toMediaCollection('order_files');
                Notification::make()->title(__('admin.order.actions.upload_done'))->success()->send();
            });
    }

    private function deleteFileAction(): Action
    {
        return Action::make('deleteFile')
            ->label(__('admin.order.actions.delete_file'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Order $record): bool => $this->canManage() && $record->getMedia('order_files')->isNotEmpty())
            ->schema([
                Select::make('media_id')->label(__('admin.order.actions.file'))->required()->native(false)
                    ->options(fn (Order $record): array => $record->getMedia('order_files')->mapWithKeys(fn ($media) => [$media->id => $media->file_name])->all()),
            ])
            ->requiresConfirmation()
            ->action(function (array $data, Order $record): void {
                $record->getMedia('order_files')->firstWhere('id', (int) $data['media_id'])?->delete();
                Notification::make()->title(__('admin.order.actions.delete_file_done'))->success()->send();
            });
    }
}
